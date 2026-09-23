<?php

declare(strict_types=1);

namespace App\Http;

use App\Core\Security\Sanitizer;

class Request
{
    private array $getData;
    private array $postData;
    private string $method;
    private string $uri;
    private array $headers;
    private array $server;
    private string $rawBody = '';

    public function __construct(
        ?array $server = null,
        ?array $get = null,
        ?array $post = null
    ) {
        $server = $server ?? $_SERVER;
        $this->getData = Sanitizer::clean_data($get ?? $_GET);
        $this->postData = $this->parsePost($post ?? $_POST, $server);
        $this->method = strtoupper($server['REQUEST_METHOD'] ?? 'GET');

        $uri = $server['REQUEST_URI'] ?? '/';
        if (($pos = strpos($uri, '?')) !== false) {
            $uri = substr($uri, 0, $pos);
        }
        $this->uri = $uri;

        $this->headers = $this->parseHeaders($server);
        $this->server = $server;
    }

    public function post(): array
    {
        return $this->postData;
    }

    public function get(string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $this->getData;
        }
        return $this->getData[$key] ?? $default;
    }

    public function all(): array
    {
        return array_merge($this->getData, $this->postData);
    }

    public function input(string $key, mixed $default = null): mixed
    {
        return $this->all()[$key] ?? $default;
    }

    public function rawBody(): string
    {
        return $this->rawBody;
    }

    public function method(): string
    {
        return $this->method;
    }

    public function uri(): string
    {
        return $this->uri;
    }

    public function header(string $key, ?string $default = null): ?string
    {
        $key = strtolower($key);
        return $this->headers[$key] ?? $default;
    }

    public function ip(): string
    {
        return $this->server['REMOTE_ADDR'] ?? '0.0.0.0';
    }

    public function userAgent(): string
    {
        return $this->server['HTTP_USER_AGENT'] ?? 'Unknown';
    }

    private function parsePost(array $post, array $server): array
    {
        $this->rawBody = (string) file_get_contents('php://input');

        if (!empty($post)) {
            return Sanitizer::clean_data($post);
        }

        $contentType = $server['CONTENT_TYPE'] ?? '';
        if (str_contains($contentType, 'application/json')) {
            $data = json_decode($this->rawBody, true);
            return is_array($data) ? Sanitizer::clean_data($data) : [];
        }

        return [];
    }

    private function parseHeaders(array $server): array
    {
        $headers = [];
        foreach ($server as $key => $value) {
            if (str_starts_with($key, 'HTTP_')) {
                $name = strtolower(str_replace('_', '-', substr($key, 5)));
                $headers[$name] = $value;
            }
        }
        if (isset($server['CONTENT_TYPE'])) {
            $headers['content-type'] = $server['CONTENT_TYPE'];
        }
        if (isset($server['CONTENT_LENGTH'])) {
            $headers['content-length'] = $server['CONTENT_LENGTH'];
        }
        return $headers;
    }
}
