<?php

namespace App\Core;

class ErrorRenderer
{
    protected static array $errorTypes = [
        E_ERROR             => 'Error',
        E_WARNING           => 'Warning',
        E_PARSE             => 'Parse Error',
        E_NOTICE            => 'Notice',
        E_CORE_ERROR        => 'Core Error',
        E_CORE_WARNING      => 'Core Warning',
        E_COMPILE_ERROR     => 'Compile Error',
        E_COMPILE_WARNING   => 'Compile Warning',
        E_USER_ERROR        => 'User Error',
        E_USER_WARNING      => 'User Warning',
        E_USER_NOTICE       => 'User Notice',
        E_STRICT            => 'Strict',
        E_RECOVERABLE_ERROR => 'Recoverable Error',
        E_DEPRECATED        => 'Deprecated',
        E_USER_DEPRECATED   => 'User Deprecated',
    ];

    protected static array $severityColors = [
        E_ERROR             => '#e74c3c',
        E_WARNING           => '#f39c12',
        E_PARSE             => '#8e44ad',
        E_NOTICE            => '#3498db',
        E_CORE_ERROR        => '#e74c3c',
        E_CORE_WARNING      => '#f39c12',
        E_COMPILE_ERROR     => '#8e44ad',
        E_COMPILE_WARNING   => '#f39c12',
        E_USER_ERROR        => '#e74c3c',
        E_USER_WARNING      => '#f39c12',
        E_USER_NOTICE       => '#3498db',
        E_STRICT            => '#95a5a6',
        E_RECOVERABLE_ERROR => '#e74c3c',
        E_DEPRECATED        => '#1abc9c',
        E_USER_DEPRECATED   => '#1abc9c',
    ];

    public function renderDebug(\Throwable $exception, int $statusCode, string $statusText, string $basePath): void
    {
        $errorType = $this->getErrorType($exception);
        $severityColor = $this->getSeverityColor($exception);
        $message = $exception->getMessage();
        $file = $exception->getFile();
        $line = $exception->getLine();
        $code = $exception->getCode();
        $codeContext = $this->getCodeContext($file, $line, $basePath);
        $variables = $this->getVariables($exception);
        $traceFrames = $this->getTraceFrames($exception);
        $previous = $exception->getPrevious();
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Error <?= $statusCode ?> — <?= htmlspecialchars($errorType) ?></title>
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;background:#1a1a2e;color:#e0e0e0;line-height:1.6;min-height:100vh}
.error-container{max-width:1000px;margin:0 auto;padding:24px}
.header{background:linear-gradient(135deg,#16213e,#0f3460);border-radius:12px;padding:32px;margin-bottom:24px;border-left:6px solid <?= $severityColor ?>;box-shadow:0 4px 20px rgba(0,0,0,.3)}
.header .status-code{font-size:64px;font-weight:800;color:<?= $severityColor ?>;line-height:1;margin-bottom:8px}
.header .status-text{font-size:14px;text-transform:uppercase;letter-spacing:2px;color:#8899aa;margin-bottom:12px}
.header .error-type-badge{display:inline-block;padding:4px 14px;border-radius:20px;font-size:13px;font-weight:600;background:<?= $severityColor ?>22;color:<?= $severityColor ?>;border:1px solid <?= $severityColor ?>55;margin-bottom:12px}
.header .error-message{font-size:18px;color:#e8e8e8;font-weight:500;word-break:break-word}
.section{background:#16213e;border-radius:12px;padding:24px;margin-bottom:20px;box-shadow:0 2px 12px rgba(0,0,0,.2)}
.section-title{font-size:13px;text-transform:uppercase;letter-spacing:1.5px;color:#8899aa;margin-bottom:16px;padding-bottom:8px;border-bottom:1px solid #1a2744;display:flex;align-items:center;gap:8px}
.section-title svg{width:16px;height:16px;fill:#8899aa}
.info-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:16px}
.info-item{background:#0f1a30;border-radius:8px;padding:14px;border:1px solid #1a2744}
.info-item .label{font-size:11px;text-transform:uppercase;letter-spacing:1px;color:#5a6a7a;margin-bottom:4px}
.info-item .value{font-size:14px;color:#c8d6e5;font-family:'SF Mono',Consolas,'Liberation Mono',Menlo,monospace;word-break:break-all}
.code-block{background:#0a0f1e;border-radius:8px;overflow:hidden;border:1px solid #1a2744}
.code-line{display:flex;font-family:'SF Mono',Consolas,'Liberation Mono',Menlo,monospace;font-size:13px;line-height:1.7;transition:background .15s}
.code-line:hover{background:#1a274444}
.code-line.highlight{background:<?= $severityColor ?>18;border-left:3px solid <?= $severityColor ?>}
.code-line:not(.highlight){border-left:3px solid transparent}
.code-line-num{min-width:50px;padding:0 12px;text-align:right;color:#3a4a5a;user-select:none;font-size:12px}
.code-line-content{padding:0 16px;white-space:pre;overflow-x:auto;color:#c8d6e5}
.kw{color:#c678dd}.str{color:#98c379}.num{color:#d19a66}.cm{color:#5c6370;font-style:italic}.fn{color:#61afef}.var{color:#e06c75}.op{color:#56b6c2}.cls{color:#e5c07b}.ns{color:#e5c07b}.type{color:#56b6c2}.bool{color:#d19a66}.null{color:#d19a66}.inc{color:#c678dd}
.trace-table{width:100%;border-collapse:collapse}
.trace-table th{text-align:left;padding:10px 14px;font-size:11px;text-transform:uppercase;letter-spacing:1px;color:#5a6a7a;border-bottom:1px solid #1a2744;background:#0f1a30}
.trace-table td{padding:10px 14px;border-bottom:1px solid #1a274415;font-size:13px;font-family:'SF Mono',Consolas,'Liberation Mono',Menlo,monospace;vertical-align:top}
.trace-table tr:hover td{background:#1a274433}
.trace-table .frame-num{color:#3a4a5a;width:40px;text-align:center}
.trace-table .frame-call{color:#c8d6e5;white-space:nowrap}
.trace-table .frame-location{color:#5a6a7a;font-size:12px;word-break:break-all}
.trace-table .frame-location a{color:#3498db;text-decoration:none}
.trace-table .frame-location a:hover{text-decoration:underline}
.trace-table .frame-line{color:#d19a66}
.vars-table{width:100%;border-collapse:collapse}
.vars-table th{text-align:left;padding:10px 14px;font-size:11px;text-transform:uppercase;letter-spacing:1px;color:#5a6a7a;border-bottom:1px solid #1a2744;background:#0f1a30}
.vars-table td{padding:10px 14px;border-bottom:1px solid #1a274415;font-size:13px;vertical-align:top}
.vars-table .var-name{font-family:'SF Mono',Consolas,'Liberation Mono',Menlo,monospace;color:#e06c75;font-weight:600;white-space:nowrap}
.vars-table .var-value{font-family:'SF Mono',Consolas,'Liberation Mono',Menlo,monospace;color:#98c379;word-break:break-all;max-width:600px;overflow:hidden;text-overflow:ellipsis}
.vars-table .var-type{font-size:11px;color:#5a6a7a;text-transform:uppercase;margin-right:6px}
.previous-exception{border-left:3px solid #f39c12;padding:12px 16px;background:#0f1a30;border-radius:0 8px 8px 0;margin-top:12px}
.previous-exception .pe-title{font-size:12px;text-transform:uppercase;letter-spacing:1px;color:#f39c12;margin-bottom:6px}
.previous-exception .pe-class{font-size:13px;color:#c8d6e5;font-family:'SF Mono',Consolas,monospace}
.previous-exception .pe-message{font-size:14px;color:#e0e0e0;margin-top:4px}
.empty-state{color:#3a4a5a;font-style:italic;padding:20px;text-align:center}
.footer{text-align:center;padding:20px;color:#3a4a5a;font-size:12px}
.footer span{color:#5a6a7a}
.collapsible{cursor:pointer;user-select:none}
.collapsible::before{content:'▼';display:inline-block;margin-right:6px;font-size:10px;transition:transform .2s}
.collapsible.collapsed::before{transform:rotate(-90deg)}
.collapsible.collapsed+.collapse-content{display:none}
@media(max-width:768px){
    .error-container{padding:12px}
    .header{padding:20px}
    .header .status-code{font-size:48px}
    .info-grid{grid-template-columns:1fr}
    .trace-table{font-size:12px}
}
</style>
</head>
<body>
<div class="error-container">
<div class="header">
    <div class="status-code"><?= $statusCode ?></div>
    <div class="status-text"><?= htmlspecialchars($statusText) ?></div>
    <div class="error-type-badge"><?= htmlspecialchars($errorType) ?></div>
    <div class="error-message"><?= nl2br(htmlspecialchars($message)) ?></div>
</div>
<div class="section">
    <div class="section-title"><svg viewBox="0 0 16 16"><path d="M8 1a7 7 0 100 14A7 7 0 008 1zm0 2a1 1 0 110 2 1 1 0 010-2zm2 8H6V6h4v5z"/></svg>Información del Error</div>
    <div class="info-grid">
        <div class="info-item"><div class="label">Tipo</div><div class="value"><?= htmlspecialchars(get_class($exception)) ?></div></div>
        <div class="info-item"><div class="label">Código</div><div class="value"><?= $code !== 0 ? $code : '—' ?></div></div>
        <div class="info-item" style="grid-column:1/-1"><div class="label">Archivo</div><div class="value"><?= htmlspecialchars($this->relativePath($file, $basePath)) ?></div></div>
        <div class="info-item"><div class="label">Línea</div><div class="value"><?= $line ?: '—' ?></div></div>
        <div class="info-item"><div class="label">Timestamp</div><div class="value"><?= date('Y-m-d H:i:s') ?></div></div>
    </div>
<?php if ($previous): ?>
    <div class="previous-exception" style="margin-top:16px">
        <div class="pe-title">Excepción Anterior</div>
        <div class="pe-class"><?= htmlspecialchars(get_class($previous)) ?></div>
        <div class="pe-message"><?= htmlspecialchars($previous->getMessage()) ?></div>
    </div>
<?php endif; ?>
</div>
<?php if ($codeContext): ?>
<div class="section">
    <div class="section-title"><svg viewBox="0 0 16 16"><path d="M5.854 4.854a.5.5 0 10-.708-.708l-3.5 3.5a.5.5 0 000 .708l3.5 3.5a.5.5 0 00.708-.708L2.707 8l3.147-3.146zm4.292 0a.5.5 0 01.708-.708l3.5 3.5a.5.5 0 010 .708l-3.5 3.5a.5.5 0 01-.708-.708L13.293 8l-3.147-3.146z"/></svg>Código Fuente</div>
    <div class="code-block">
<?php foreach ($codeContext['lines'] as $ctx): ?>
        <div class="code-line<?= $ctx['is_line'] ? ' highlight' : '' ?>">
            <span class="code-line-num"><?= $ctx['num'] ?></span>
            <span class="code-line-content"><?= $this->highlightSyntax($ctx['code']) ?></span>
        </div>
<?php endforeach; ?>
    </div>
</div>
<?php endif; ?>
<?php if (!empty($variables)): ?>
<div class="section">
    <div class="section-title"><svg viewBox="0 0 16 16"><path d="M2 2h12v2H2zm0 4h8v2H2zm0 4h10v2H2z"/></svg>Variables</div>
    <div class="vars-table" style="display:table;width:100%">
        <div style="display:table-row;background:#0f1a30">
            <div style="display:table-cell;padding:10px 14px;font-size:11px;text-transform:uppercase;letter-spacing:1px;color:#5a6a7a;border-bottom:1px solid #1a2744;width:200px">Variable</div>
            <div style="display:table-cell;padding:10px 14px;font-size:11px;text-transform:uppercase;letter-spacing:1px;color:#5a6a7a;border-bottom:1px solid #1a2744">Valor</div>
        </div>
<?php foreach ($variables as $varName => $varValue): ?>
        <div style="display:table-row">
            <div style="display:table-cell;padding:10px 14px;border-bottom:1px solid #1a274415;font-family:'SF Mono',Consolas,'Liberation Mono',Menlo,monospace;color:#e06c75;font-weight:600;font-size:13px;vertical-align:top">$<?= htmlspecialchars($varName) ?></div>
            <div style="display:table-cell;padding:10px 14px;border-bottom:1px solid #1a274415;font-family:'SF Mono',Consolas,'Liberation Mono',Menlo,monospace;color:#98c379;font-size:13px;word-break:break-all;max-width:600px;overflow:hidden;text-overflow:ellipsis"><?= htmlspecialchars($this->formatVarValue($varValue)) ?></div>
        </div>
<?php endforeach; ?>
    </div>
</div>
<?php endif; ?>
<div class="section">
    <div class="section-title collapsible" onclick="this.classList.toggle('collapsed');this.nextElementSibling.classList.toggle('hidden')">
        <svg viewBox="0 0 16 16"><path d="M1.5 3a.5.5 0 000 1h14a.5.5 0 000-1h-14zm0 4a.5.5 0 000 1h14a.5.5 0 000-1h-14zm0 4a.5.5 0 000 1h14a.5.5 0 000-1h-14z"/></svg>
        Stack Trace (<?= count($traceFrames) ?> frames)
    </div>
    <div class="collapse-content">
        <table class="trace-table">
            <thead><tr><th>#</th><th>Llamada</th><th>Ubicación</th></tr></thead>
            <tbody>
<?php foreach ($traceFrames as $frame): ?>
                <tr>
                    <td class="frame-num"><?= $frame['index'] ?></td>
                    <td class="frame-call"><?= htmlspecialchars($frame['callable']) ?><?= !empty($frame['args']) ? '<span style="color:#5a6a7a;font-size:11px">(' . count($frame['args']) . ' args)</span>' : '' ?></td>
                    <td class="frame-location">
<?php if ($frame['file']): ?>
                        <a href="file:///<?= $frame['file'] ?>" title="<?= htmlspecialchars($frame['file']) ?>"><?= htmlspecialchars($this->relativePath($frame['file'], $basePath)) ?></a>
                        <span class="frame-line">:<?= $frame['line'] ?></span>
<?php else: ?>
                        <span style="color:#3a4a5a">[interno]</span>
<?php endif; ?>
                    </td>
                </tr>
<?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<div class="footer"><span>Error Handler</span> &mdash; <?= date('Y-m-d H:i:s') ?> &mdash; <?= htmlspecialchars($_SERVER['SERVER_SOFTWARE'] ?? 'PHP') ?></div>
</div>
<script>
document.querySelectorAll('.collapsible').forEach(function(el){
    el.addEventListener('click', function(){
        var content = this.nextElementSibling;
        if(content) content.classList.toggle('hidden');
    });
});
</script>
</body>
</html>
<?php
    }

    public function renderProduction(int $statusCode, string $statusText): void
    {
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Error <?= $statusCode ?> — <?= htmlspecialchars($statusText) ?></title>
<style>
body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;background:#f8f9fa;color:#343a40;display:flex;justify-content:center;align-items:center;min-height:100vh;margin:0}
.container{text-align:center;padding:40px;max-width:500px}
.code{font-size:120px;font-weight:800;color:#dee2e6;line-height:1}
.message{font-size:20px;color:#6c757d;margin:16px 0 8px}
.status-label{font-size:14px;text-transform:uppercase;letter-spacing:2px;color:#adb5bd;margin-bottom:24px}
.divider{width:60px;height:3px;background:#dee2e6;margin:0 auto 24px;border-radius:2px}
.home-link{display:inline-block;padding:10px 24px;background:#343a40;color:#fff;text-decoration:none;border-radius:6px;font-size:14px;transition:background .2s}
.home-link:hover{background:#495057}
</style></head>
<body>
<div class="container">
    <div class="code"><?= $statusCode ?></div>
    <div class="message">
<?php if ($statusCode === 404): ?>
        La página que buscas no existe.
<?php elseif ($statusCode === 403): ?>
        No tienes permiso para acceder a este recurso.
<?php elseif ($statusCode === 405): ?>
        El método HTTP no está permitido para esta ruta.
<?php elseif ($statusCode === 401): ?>
        Debes autenticarte para acceder a este recurso.
<?php elseif ($statusCode === 422): ?>
        Los datos enviados no son válidos.
<?php elseif ($statusCode === 400): ?>
        La solicitud contiene errores y no puede ser procesada.
<?php elseif ($statusCode === 409): ?>
        La solicitud entra en conflicto con el estado actual del servidor.
<?php elseif ($statusCode === 429): ?>
        Has excedido el límite de solicitudes. Inténtalo de nuevo más tarde.
<?php else: ?>
        Ha ocurrido un error inesperado. Por favor, intenta más tarde.
<?php endif; ?>
    </div>
    <div class="status-label"><?= htmlspecialchars($statusText) ?></div>
    <div class="divider"></div>
    <a href="/" class="home-link">Volver al Inicio</a>
</div>
</body>
</html>
<?php
    }

    public function renderFallback(\Throwable $exception): void
    {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        if (!headers_sent()) {
            http_response_code(500);
        }

        echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Error</title></head><body>';
        echo '<h1>500 - Internal Server Error</h1>';
        echo '<p>' . htmlspecialchars($exception->getMessage()) . '</p>';
        echo '<p>' . htmlspecialchars($exception->getFile()) . ':' . $exception->getLine() . '</p>';
        echo '</body></html>';
        exit;
    }

    protected function getErrorType(\Throwable $exception): string
    {
        if ($exception instanceof \ErrorException) {
            $severity = $exception->getSeverity();
            return self::$errorTypes[$severity] ?? 'Error';
        }

        $class = get_class($exception);
        $shortClass = basename(str_replace('\\', '/', $class));
        return $shortClass;
    }

    protected function getSeverityColor(\Throwable $exception): string
    {
        if ($exception instanceof \ErrorException) {
            return self::$severityColors[$exception->getSeverity()] ?? '#e74c3c';
        }
        return '#e74c3c';
    }

    protected function getCodeContext(string $file, int $line, string $basePath, int $context = 7): ?array
    {
        if (!is_file($file)) {
            return null;
        }

        $lines = @file($file);
        if ($lines === false) {
            return null;
        }

        $start = max(0, $line - $context - 1);
        $end = min(count($lines) - 1, $line + $context - 1);

        $code = [];
        for ($i = $start; $i <= $end; $i++) {
            $code[] = [
                'num' => $i + 1,
                'code' => rtrim($lines[$i]),
                'is_line' => ($i + 1) === $line,
            ];
        }

        return [
            'start' => $start + 1,
            'end' => $end + 1,
            'lines' => $code,
        ];
    }

    protected function getVariables(\Throwable $exception): array
    {
        $trace = $exception->getTrace();
        $vars = [];

        if (!empty($trace[0]['file'])) {
            $file = $trace[0]['file'];
            $line = $trace[0]['line'] ?? 0;

            if (is_file($file)) {
                $lines = @file($file);
                if ($lines && $line > 0 && $line <= count($lines)) {
                    $start = max(0, $line - 15);
                    $end = min(count($lines) - 1, $line + 5);
                    $code = implode('', array_slice($lines, $start, $end - $start + 1));

                    if (preg_match_all('/\$([a-zA-Z_][a-zA-Z0-9_]*)/', $code, $matches)) {
                        $varNames = array_unique($matches[1]);
                        foreach ($varNames as $name) {
                            if (isset($GLOBALS[$name]) && !is_resource($GLOBALS[$name])) {
                                $vars[$name] = $this->sanitizeVariable($GLOBALS[$name]);
                            }
                        }
                    }
                }
            }
        }

        if (!empty($trace)) {
            foreach ($trace as $i => $frame) {
                if (!empty($frame['args'])) {
                    foreach ($frame['args'] as $arg) {
                        if (is_string($arg) && strlen($arg) < 200) {
                            $vars["arg#{$i}"] = $arg;
                        } elseif (is_numeric($arg)) {
                            $vars["arg#{$i}"] = $arg;
                        }
                    }
                }
                if ($i >= 2) break;
            }
        }

        return $vars;
    }

    protected function sanitizeVariable(mixed $value): mixed
    {
        if (is_null($value)) return null;
        if (is_bool($value)) return $value ? 'true' : 'false';
        if (is_resource($value)) return '[resource]';
        if (is_object($value)) return get_class($value);
        if (is_array($value)) {
            $result = [];
            foreach ($value as $k => $v) {
                $result[$k] = $this->sanitizeVariable($v);
            }
            return $result;
        }
        if (is_string($value)) {
            if (strlen($value) > 500) {
                return substr($value, 0, 500) . '... (' . strlen($value) . ' bytes)';
            }
            return $value;
        }
        return $value;
    }

    protected function getTraceFrames(\Throwable $exception): array
    {
        $trace = $exception->getTrace();
        $frames = [];

        foreach ($trace as $i => $frame) {
            $class = $frame['class'] ?? '';
            $function = $frame['function'] ?? '';
            $file = $frame['file'] ?? null;
            $line = $frame['line'] ?? null;

            if ($class) {
                $callable = $class . '::' . $function;
            } elseif ($function) {
                $callable = $function . '()';
            } else {
                $callable = '{main}';
            }

            $frames[] = [
                'index' => $i,
                'callable' => $callable,
                'file' => $file,
                'line' => $line,
                'args' => $frame['args'] ?? [],
            ];
        }

        return $frames;
    }

    protected function highlightSyntax(string $code): string
    {
        if ($code === '') {
            return '';
        }

        try {
            $result = @highlight_string($code, true);

            if ($result === false) {
                return htmlspecialchars($code, ENT_QUOTES, 'UTF-8');
            }

            $result = preg_replace('~^<pre><code[^>]*>~', '', $result);
            $result = preg_replace('~</code></pre>$~', '', $result);

            $colorMap = [
                '#000000' => 'kw',
                '#0000BB' => 'kw',
                '#0000FF' => 'kw',
                '#FF8000' => 'str',
                '#DD0000' => 'str',
                '#FF0000' => 'str',
                '#007700' => 'cm',
                '#008000' => 'cm',
            ];

            $result = preg_replace_callback(
                '~<span style="color: #[0-9A-Fa-f]{6}">~',
                function ($matches) use ($colorMap) {
                    preg_match('/#[0-9A-Fa-f]{6}/', $matches[0], $colorMatch);
                    $color = strtoupper($colorMatch[0]);
                    $class = $colorMap[$color] ?? 'kw';
                    return '<span class="' . $class . '">';
                },
                $result
            );

            return $result;
        } catch (\Throwable $e) {
            return htmlspecialchars($code, ENT_QUOTES, 'UTF-8');
        }
    }

    protected function relativePath(string $file, string $basePath): string
    {
        $relative = str_replace($basePath, '', $file);
        return ltrim($relative, '/\\') ?: $file;
    }

    protected function formatVarValue(mixed $value): string
    {
        if (is_null($value)) return 'null';
        if (is_bool($value)) return $value ? 'true' : 'false';
        if (is_string($value)) return '"' . $value . '"';
        if (is_array($value)) return 'array(' . count($value) . ' items)';
        if (is_object($value)) return get_class($value);
        if (is_resource($value)) return 'resource';
        return (string) $value;
    }
}
