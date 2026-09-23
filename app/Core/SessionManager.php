<?php

declare(strict_types=1);

namespace App\Core;

use App\Framework\Session\SessionManagerInterface;

class SessionManager implements SessionManagerInterface
{
    private bool $started = false;
    private string $sessionName = 'APP_SESSION';
    private int $lifetime = 7200;
    private string $path = '/';
    private string $domain = '';
    private bool $secure = false;
    private bool $httponly = true;
    private string $samesite = 'Strict';

    public function start(array $config = []): bool
    {
        if ($this->started || session_status() !== PHP_SESSION_NONE) {
            return $this->started;
        }

        $this->sessionName = $config['name'] ?? $this->sessionName;
        $this->lifetime = (int) ($config['lifetime'] ?? $this->lifetime);
        $this->secure = $this->resolveSecureFlag($config);
        $this->httponly = $config['httponly'] ?? $this->httponly;
        $this->samesite = $config['samesite'] ?? $this->samesite;

        session_name($this->sessionName);

        ini_set('session.gc_probability', $config['gc_probability'] ?? 1);
        ini_set('session.gc_divisor', $config['gc_divisor'] ?? 100);
        ini_set('session.gc_maxlifetime', $config['gc_maxlifetime'] ?? $this->lifetime);

        if (!empty($config['save_path'])) {
            session_save_path($config['save_path']);
        }

        session_set_cookie_params([
            'lifetime' => $this->lifetime,
            'path' => $this->path,
            'domain' => $this->domain,
            'secure' => $this->secure,
            'httponly' => $this->httponly,
            'samesite' => $this->samesite,
        ]);

        if (empty($_COOKIE[$this->sessionName])) {
            session_id(bin2hex(random_bytes(16)));
        }

        if (!session_start()) {
            return false;
        }

        $this->started = true;
        $this->regenerateId();
        if ($this->isExpired()) {
            $this->destroy();
            return false;
        }

        $_SESSION['_last_activity'] = time();
        return true;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $this->ensureStarted();
        if (str_contains($key, '.')) {
            return $this->getNested($key, $default);
        }
        return $_SESSION[$key] ?? $default;
    }

    public function set(string $key, mixed $value): void
    {
        $this->ensureStarted();
        if (str_contains($key, '.')) {
            $this->setNested($key, $value);
            return;
        }
        $_SESSION[$key] = $value;
    }

    public function has(string $key): bool
    {
        $this->ensureStarted();
        if (str_contains($key, '.')) {
            return $this->hasNested($key);
        }
        return isset($_SESSION[$key]);
    }

    public function remove(string $key): void
    {
        $this->ensureStarted();
        if (str_contains($key, '.')) {
            $this->removeNested($key);
            return;
        }
        unset($_SESSION[$key]);
    }

    public function pull(string $key, mixed $default = null): mixed
    {
        $value = $this->get($key, $default);
        $this->remove($key);
        return $value;
    }

    public function destroy(): void
    {
        if (!$this->started) {
            return;
        }
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params['path'], $params['domain'],
                $params['secure'], $params['httponly']
            );
        }
        session_destroy();
        $this->started = false;
    }

    public function save(): void
    {
        if ($this->started) {
            session_write_close();
            $this->started = false;
        }
    }

    public function getId(): string
    {
        $this->ensureStarted();
        return session_id();
    }

    public function regenerateId(bool $deleteOldSession = true): void
    {
        $this->ensureStarted();
        if (
            !isset($_SESSION['_regenerated']) ||
            time() - $_SESSION['_regenerated'] > 1800
        ) {
            $this->doRegenerateId($deleteOldSession);
        }
    }

    /**
     * Regenera el ID de sesión sin aplicar la rotación periódica (guard 30 min).
     * Se usa tras cambios de privilegio (login) para blindar contra session fixation.
     */
    public function regenerateIdForced(bool $deleteOldSession = true): void
    {
        $this->ensureStarted();
        $this->doRegenerateId($deleteOldSession);
    }

    public function isExpired(): bool
    {
        if (!isset($_SESSION['_last_activity'])) {
            return false;
        }
        return (time() - $_SESSION['_last_activity']) > $this->lifetime;
    }

    public function validate(): bool
    {
        if (!$this->has('_fingerprint')) {
            return false;
        }
        $stored = $_SESSION['_fingerprint'];
        return $stored === $this->generateFingerprint();
    }

    public function getName(): string
    {
        return $this->sessionName;
    }

    public function all(): array
    {
        $this->ensureStarted();
        return $_SESSION;
    }

    public function clear(): void
    {
        $this->ensureStarted();
        foreach ($_SESSION as $key => $value) {
            if ($key !== '_last_activity' && $key !== '_fingerprint') {
                unset($_SESSION[$key]);
            }
        }
    }

    private function ensureStarted(): void
    {
        if (!$this->started) {
            $this->start();
        }
    }

    /**
     * Decide el flag Secure de la cookie de sesión.
     *
     * 1. force_secure (config explícita) tiene prioridad absoluta.
     * 2. Si no hay force, comprueba HTTPS directo o el header X-Forwarded-Proto
     *    del proxy/terminador TLS (InfinityFree).
     * 3. El valor 'secure' legacy de la config actúa solo como refuerzo.
     */
    private function resolveSecureFlag(array $config): bool
    {
        if (!empty($config['force_secure'])) {
            return true;
        }
        return $this->detectSecureRequest() || (bool) ($config['secure'] ?? false);
    }

    private function detectSecureRequest(): bool
    {
        if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
            return true;
        }
        $forwardedProto = (string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '');
        return stripos($forwardedProto, 'https') !== false;
    }

    private function doRegenerateId(bool $deleteOldSession): void
    {
        session_regenerate_id($deleteOldSession);
        $_SESSION['_regenerated'] = time();
        $_SESSION['_fingerprint'] = $this->generateFingerprint();
    }

    private function generateFingerprint(): string
    {
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        $ipPrefix = substr(md5($ip), 0, 8);
        return hash('sha256', $userAgent . $ipPrefix);
    }

    private function getNested(string $key, mixed $default = null): mixed
    {
        $keys = explode('.', $key);
        $array = $_SESSION;
        foreach ($keys as $segment) {
            if (!is_array($array) || !array_key_exists($segment, $array)) {
                return $default;
            }
            $array = $array[$segment];
        }
        return $array;
    }

    private function setNested(string $key, mixed $value): void
    {
        $keys = explode('.', $key);
        $array = &$_SESSION;
        foreach ($keys as $i => $segment) {
            if ($i === count($keys) - 1) {
                $array[$segment] = $value;
            } else {
                if (!isset($array[$segment]) || !is_array($array[$segment])) {
                    $array[$segment] = [];
                }
                $array = &$array[$segment];
            }
        }
    }

    private function hasNested(string $key): bool
    {
        $keys = explode('.', $key);
        $array = $_SESSION;
        foreach ($keys as $segment) {
            if (!is_array($array) || !array_key_exists($segment, $array)) {
                return false;
            }
            $array = $array[$segment];
        }
        return true;
    }

    private function removeNested(string $key): void
    {
        $keys = explode('.', $key);
        $array = &$_SESSION;
        foreach ($keys as $i => $segment) {
            if ($i === count($keys) - 1) {
                unset($array[$segment]);
            } else {
                if (!isset($array[$segment]) || !is_array($array[$segment])) {
                    return;
                }
                $array = &$array[$segment];
            }
        }
    }
}
