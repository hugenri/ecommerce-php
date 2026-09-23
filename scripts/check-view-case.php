<?php

declare(strict_types=1);

/*
 * Verificador de mayúsculas/minúsculas en rutas de vistas.
 *
 * Linux/LiteSpeed (producción) es case-sensitive, Windows/Mac (local) no.
 * Un string de $this->view('módulo/vista') que no coincida carácter por
 * carácter con el archivo en disco solo falla en producción.
 *
 * Recorre los controladores de app/Modules/{Modulo}/Presentation/Controllers,
 * extrae cada llamada a $this->view(...), deriva el módulo desde el
 * namespace (o el tercer argumento) y comprueba la ruta resultante contra
 * los nombres reales del sistema de archivos con comparación exacta.
 *
 * Uso:
 *   php scripts/check-view-case.php
 *
 * Código de salida: 0 si todo coincide, 1 si hay discrepancias.
 */

$root = dirname(__DIR__);
$modulesBase = $root . '/app/Modules';

function moduleFromNamespace(string $content): ?string
{
    if (preg_match('/namespace\s+App\\\\Modules\\\\([A-Za-z0-9_]+)\\\\/', $content, $m)) {
        return $m[1];
    }
    return null;
}

function stripQuotes(string $s): string
{
    $s = trim($s);
    $first = $s[0] ?? '';
    if (($first === "'" || $first === '"') && strlen($s) >= 2 && substr($s, -1) === $first) {
        return substr($s, 1, -1);
    }
    return $s;
}

function splitTopLevel(string $s): array
{
    $parts = [];
    $depth = 0;
    $buf = '';
    $len = strlen($s);
    for ($i = 0; $i < $len; $i++) {
        $c = $s[$i];
        if ($c === '(' || $c === '[' || $c === '{') {
            $depth++;
        } elseif ($c === ')' || $c === ']' || $c === '}') {
            $depth--;
        }
        if ($c === ',' && $depth === 0) {
            $parts[] = trim($buf);
            $buf = '';
        } else {
            $buf .= $c;
        }
    }
    $parts[] = trim($buf);
    return $parts;
}

function caseSensitiveExists(string $base, string $rel): bool
{
    $segments = preg_split('~[\\\\/]+~', $rel);
    $current = $base;
    foreach ($segments as $seg) {
        if ($seg === '' || $seg === '.') {
            continue;
        }
        $found = false;
        $entries = @scandir($current);
        if ($entries === false) {
            return false;
        }
        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            if ($entry === $seg) {
                $current = $current . DIRECTORY_SEPARATOR . $seg;
                $found = true;
                break;
            }
        }
        if (!$found) {
            return false;
        }
    }
    return is_file($current);
}

$failures = 0;
$total = 0;

$controllers = glob($modulesBase . '/*/Presentation/Controllers/*.php');
sort($controllers);

foreach ($controllers as $file) {
    $content = file_get_contents($file);
    $module = moduleFromNamespace($content);
    if ($module === null) {
        fwrite(STDERR, "  [skip] sin namespace de módulo: {$file}\n");
        continue;
    }

    $offset = 0;
    while (($pos = strpos($content, '->view(', $offset)) !== false) {
        $start = $pos + 7;
        $depth = 0;
        $inString = null;
        $escaped = false;
        $len = strlen($content);
        $end = $len;
        for ($i = $start; $i < $len; $i++) {
            $c = $content[$i];
            if ($inString !== null) {
                if ($escaped) {
                    $escaped = false;
                    continue;
                }
                if ($c === '\\') {
                    $escaped = true;
                    continue;
                }
                if ($c === $inString) {
                    $inString = null;
                }
                continue;
            }
            if ($c === "'" || $c === '"') {
                $inString = $c;
                continue;
            }
            if ($c === '(') {
                $depth++;
            } elseif ($c === ')') {
                if ($depth === 0) {
                    $end = $i;
                    break;
                }
                $depth--;
            }
        }
        $call = substr($content, $start, $end - $start);
        $args = splitTopLevel($call);
        $offset = $start;

        $view = stripQuotes($args[0] ?? '');
        if ($view === '' || $view[0] === '$') {
            continue;
        }

        $resolvedModule = $module;
        if (count($args) >= 3) {
            $third = stripQuotes($args[2] ?? '');
            if ($third !== '' && $third[0] !== '$') {
                $resolvedModule = $third;
            }
        }

        $base = $modulesBase . '/' . $resolvedModule . '/Presentation/Views';
        $rel = $view . '.php';
        $total++;
        if (!caseSensitiveExists($base, $rel)) {
            $failures++;
            echo "MISMATCH: {$file}\n";
            echo "  view   : {$view}\n";
            echo "  módulo : {$resolvedModule}\n";
            echo "  esperado: {$base}/{$rel}\n";
        }
    }
}

echo "\n{$total} referencias a vistas analizadas. {$failures} discrepancias de mayúsculas/minúsculas.\n";
exit($failures > 0 ? 1 : 0);
