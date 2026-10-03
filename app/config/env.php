<?php
/**
 * Load EasyCalf settings from the process environment or a file that is not in git.
 *
 * Looked up in this order (already-set environment variables always win):
 * 1. The path in EASYCALF_ENV_FILE, when that variable is set
 * 2. A file next to this project, named "<project-directory>.env"
 *    (outside the project, so outside the web root when the site is this tree)
 * 3. A .env file in the project root (outside public/ when that is the document root)
 */

class EasyCalfConfigException extends RuntimeException
{
}

function easycalf_project_root(): string
{
    return dirname(__DIR__, 2);
}

function easycalf_normalize_path(string $path): string
{
    $path = str_replace('\\', '/', $path);
    if ($path === '' || $path[0] !== '/') {
        $path = getcwd() . '/' . $path;
    }

    $parts = [];
    foreach (explode('/', $path) as $part) {
        if ($part === '' || $part === '.') {
            continue;
        }
        if ($part === '..') {
            array_pop($parts);
            continue;
        }
        $parts[] = $part;
    }

    return '/' . implode('/', $parts);
}

function easycalf_path_is_inside(string $path, string $directory): bool
{
    $path = easycalf_normalize_path($path);
    $directory = rtrim(easycalf_normalize_path($directory), '/');

    return $path === $directory || str_starts_with($path, $directory . '/');
}

function easycalf_env_candidates(): array
{
    $root = easycalf_project_root();
    $candidates = [];

    $explicit = getenv('EASYCALF_ENV_FILE');
    if (is_string($explicit) && $explicit !== '') {
        $candidates[] = $explicit;
    }

    $candidates[] = dirname($root) . DIRECTORY_SEPARATOR . basename($root) . '.env';
    $candidates[] = $root . DIRECTORY_SEPARATOR . '.env';

    return $candidates;
}

function easycalf_find_env_file(): ?string
{
    foreach (easycalf_env_candidates() as $path) {
        if (is_file($path) && is_readable($path)) {
            return $path;
        }
    }

    return null;
}

function easycalf_assert_env_path_allowed(string $path): void
{
    $root = easycalf_project_root();
    foreach ([$root . '/public', $root . '/install'] as $dir) {
        if (easycalf_path_is_inside($path, $dir)) {
            throw new EasyCalfConfigException(
                'The configuration file must be stored outside the web root.'
            );
        }
    }
}

function easycalf_env_write_path(): string
{
    $explicit = getenv('EASYCALF_ENV_FILE');
    if (is_string($explicit) && $explicit !== '') {
        easycalf_assert_env_path_allowed($explicit);
        return $explicit;
    }

    // Update the file the application is already reading, so a second file
    // does not shadow the database settings.
    $existing = easycalf_find_env_file();
    if ($existing !== null) {
        easycalf_assert_env_path_allowed($existing);
        return $existing;
    }

    $root = easycalf_project_root();
    $parent = dirname($root);
    $sibling = $parent . DIRECTORY_SEPARATOR . basename($root) . '.env';
    if ($parent !== $root && is_dir($parent) && is_writable($parent)) {
        easycalf_assert_env_path_allowed($sibling);
        return $sibling;
    }

    $projectFile = $root . DIRECTORY_SEPARATOR . '.env';
    easycalf_assert_env_path_allowed($projectFile);
    return $projectFile;
}

function easycalf_parse_env_line(string $line): ?array
{
    $line = trim($line);
    if ($line === '' || str_starts_with($line, '#')) {
        return null;
    }
    if (str_starts_with($line, 'export ')) {
        $line = trim(substr($line, 7));
    }

    $eq = strpos($line, '=');
    if ($eq === false) {
        return null;
    }

    $key = trim(substr($line, 0, $eq));
    if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $key)) {
        return null;
    }

    $value = trim(substr($line, $eq + 1));
    $length = strlen($value);
    if ($length >= 2) {
        $quote = $value[0];
        if (($quote === '"' || $quote === "'") && $value[$length - 1] === $quote) {
            $value = substr($value, 1, -1);
            if ($quote === '"') {
                $value = easycalf_unescape_double_quoted($value);
            }
        }
    }

    return [$key, str_replace("\0", '', $value)];
}

function easycalf_unescape_double_quoted(string $value): string
{
    $out = '';
    $length = strlen($value);
    for ($i = 0; $i < $length; $i++) {
        if ($value[$i] !== '\\' || $i + 1 >= $length) {
            $out .= $value[$i];
            continue;
        }
        $next = $value[++$i];
        if ($next === 'n') {
            $out .= "\n";
        } elseif ($next === 'r') {
            $out .= "\r";
        } else {
            $out .= $next;
        }
    }

    return $out;
}

function easycalf_env_quote(string $value): string
{
    $escaped = '';
    $length = strlen($value);
    for ($i = 0; $i < $length; $i++) {
        $ch = $value[$i];
        if ($ch === '\\' || $ch === '"') {
            $escaped .= '\\' . $ch;
        } elseif ($ch === "\n") {
            $escaped .= '\\n';
        } elseif ($ch === "\r") {
            $escaped .= '\\r';
        } else {
            $escaped .= $ch;
        }
    }

    return '"' . $escaped . '"';
}

function easycalf_env_raw(string $key): ?string
{
    if (array_key_exists($key, $_ENV) && $_ENV[$key] !== null && $_ENV[$key] !== false) {
        return str_replace("\0", '', (string) $_ENV[$key]);
    }

    $value = getenv($key);
    if ($value === false) {
        return null;
    }

    return str_replace("\0", '', $value);
}

function easycalf_env(string $key, ?string $default = null): ?string
{
    $value = easycalf_env_raw($key);
    if ($value === null || $value === '') {
        return $default;
    }

    return $value;
}

function easycalf_env_flag(string $key, bool $default = false): bool
{
    $value = easycalf_env($key, $default ? 'true' : 'false');
    return in_array(strtolower((string) $value), ['1', 'true', 'yes', 'on'], true);
}

function easycalf_load_env(): void
{
    static $loaded = false;
    if ($loaded) {
        return;
    }
    $loaded = true;

    $path = easycalf_find_env_file();
    if ($path === null) {
        return;
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES);
    if ($lines === false) {
        return;
    }

    foreach ($lines as $line) {
        $parsed = easycalf_parse_env_line($line);
        if ($parsed === null) {
            continue;
        }
        [$key, $value] = $parsed;
        $current = easycalf_env_raw($key);
        if ($current !== null && $current !== '') {
            continue;
        }
        $_ENV[$key] = $value;
        putenv($key . '=' . $value);
    }
}

function easycalf_require_settings(array $keys): void
{
    $missing = [];
    foreach ($keys as $key) {
        if (easycalf_env($key) === null) {
            $missing[] = $key;
        }
    }

    if ($missing !== []) {
        throw new EasyCalfConfigException(
            'Missing required settings: ' . implode(', ', $missing)
            . '. Set them in the environment or in a configuration file outside the web root. See .env.example.'
        );
    }
}

function easycalf_read_env_file(string $path): array
{
    if (!is_file($path)) {
        return [];
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES);
    if ($lines === false) {
        throw new EasyCalfConfigException('Could not read the configuration file.');
    }

    $values = [];
    foreach ($lines as $line) {
        $parsed = easycalf_parse_env_line($line);
        if ($parsed === null) {
            continue;
        }
        $values[$parsed[0]] = $parsed[1];
    }

    return $values;
}

function easycalf_env_set(array $values): string
{
    foreach ($values as $key => $value) {
        if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', (string) $key)) {
            throw new EasyCalfConfigException('Invalid configuration name.');
        }
        $values[$key] = str_replace("\0", '', (string) $value);
    }

    $path = easycalf_env_write_path();
    $dir = dirname($path);
    if (!is_dir($dir) || !is_writable($dir)) {
        throw new EasyCalfConfigException(
            'Could not write the configuration file. Choose a directory outside the web root that PHP can write to, or set EASYCALF_ENV_FILE.'
        );
    }

    $existing = easycalf_read_env_file($path);
    foreach ($values as $key => $value) {
        $existing[$key] = $value;
    }

    $lines = [
        '# Local EasyCalf configuration. Do not commit this file.',
        '',
    ];
    foreach ($existing as $key => $value) {
        $lines[] = $key . '=' . easycalf_env_quote($value);
    }

    $written = file_put_contents($path, implode("\n", $lines) . "\n", LOCK_EX);
    if ($written === false) {
        throw new EasyCalfConfigException('Could not write the configuration file.');
    }

    @chmod($path, 0600);

    foreach ($values as $key => $value) {
        $_ENV[$key] = $value;
        putenv($key . '=' . $value);
    }

    return $path;
}

function easycalf_configure_error_display(): void
{
    if (!defined('APP_DEBUG')) {
        define('APP_DEBUG', easycalf_env_flag('APP_DEBUG'));
    }

    error_reporting(E_ALL);
    ini_set('log_errors', '1');
    $display = APP_DEBUG ? '1' : '0';
    ini_set('display_errors', $display);
    ini_set('display_startup_errors', $display);
}

function easycalf_debug_enabled(): bool
{
    return defined('APP_DEBUG') && APP_DEBUG;
}

function easycalf_config_is_complete(): bool
{
    easycalf_load_env();
    foreach (['DB_HOST', 'DB_NAME', 'DB_USER', 'DB_PASS'] as $key) {
        if (easycalf_env($key) === null) {
            return false;
        }
    }

    return true;
}
