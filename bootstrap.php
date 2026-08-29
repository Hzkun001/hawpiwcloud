<?php
declare(strict_types=1);

const AUTH_IDLE_TIMEOUT = 1800;

function configurationError(string $detail): never
{
    error_log('hawpiwcloud configuration error: ' . $detail);
    http_response_code(503);
    echo 'Aplikasi belum dikonfigurasi dengan benar.';
    exit;
}

function isHttpsRequest(): bool
{
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (int)($_SERVER['SERVER_PORT'] ?? 0) === 443;
}

ini_set('session.use_strict_mode', '1');
ini_set('session.use_only_cookies', '1');
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'secure' => isHttpsRequest(),
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_start();

function csrfToken(): string
{
    if (!isset($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token']) || $_SESSION['csrf_token'] === '') {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function isValidCsrfToken(?string $token): bool
{
    return $token !== null && hash_equals(csrfToken(), $token);
}

function redirectWithStatus(string $status): never
{
    header('Location: index.php?status=' . rawurlencode($status));
    exit;
}

function redirectToLogin(): never
{
    header('Location: login.php');
    exit;
}

function clearAuthentication(): void
{
    unset($_SESSION['authenticated'], $_SESSION['last_activity']);
}

function isAuthenticated(): bool
{
    if (($_SESSION['authenticated'] ?? false) !== true) {
        return false;
    }

    $lastActivity = (int)($_SESSION['last_activity'] ?? 0);
    if ($lastActivity === 0 || time() - $lastActivity > AUTH_IDLE_TIMEOUT) {
        clearAuthentication();
        return false;
    }

    return true;
}

function requireAuthentication(): void
{
    if (!isAuthenticated()) {
        redirectToLogin();
    }

    $_SESSION['last_activity'] = time();
}

function configuredPasswordHash(): string
{
    $hash = getenv('HAWPIWCLOUD_PASSWORD_HASH');
    if (!is_string($hash) || $hash === '' || password_get_info($hash)['algo'] === null) {
        configurationError('HAWPIWCLOUD_PASSWORD_HASH is missing or invalid.');
    }

    return $hash;
}

function pathIsInside(string $path, string $parent): bool
{
    $path = rtrim($path, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    $parent = rtrim($parent, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

    return str_starts_with($path, $parent);
}

function privateDataDirectory(): string
{
    static $dataDirectory;

    if (is_string($dataDirectory)) {
        return $dataDirectory;
    }

    $configured = getenv('HAWPIWCLOUD_DATA_DIR');
    $resolved = is_string($configured) && $configured !== '' ? realpath($configured) : false;
    $appRoot = realpath(__DIR__);
    $documentRoot = realpath((string)($_SERVER['DOCUMENT_ROOT'] ?? '')) ?: $appRoot;

    if ($resolved === false || $appRoot === false || $documentRoot === false || !is_dir($resolved)) {
        configurationError('HAWPIWCLOUD_DATA_DIR must reference an existing directory.');
    }

    if (pathIsInside($resolved, $appRoot) || pathIsInside($resolved, $documentRoot)) {
        configurationError('HAWPIWCLOUD_DATA_DIR must be outside the public document root.');
    }

    if (!is_readable($resolved) || !is_writable($resolved)) {
        configurationError('HAWPIWCLOUD_DATA_DIR must be readable and writable.');
    }

    $dataDirectory = rtrim($resolved, DIRECTORY_SEPARATOR);
    return $dataDirectory;
}

function storageDirectory(): string
{
    static $storageDirectory;

    if (is_string($storageDirectory)) {
        return $storageDirectory;
    }

    $dataDirectory = privateDataDirectory();
    $resolved = realpath($dataDirectory . DIRECTORY_SEPARATOR . 'files');
    if ($resolved === false || !is_dir($resolved) || !is_readable($resolved) || !is_writable($resolved)) {
        configurationError('HAWPIWCLOUD_DATA_DIR/files must exist and be readable and writable.');
    }

    $appRoot = realpath(__DIR__);
    $documentRoot = realpath((string)($_SERVER['DOCUMENT_ROOT'] ?? '')) ?: $appRoot;
    if ($appRoot === false || $documentRoot === false
        || !pathIsInside($resolved, $dataDirectory)
        || pathIsInside($resolved, $appRoot)
        || pathIsInside($resolved, $documentRoot)) {
        configurationError('HAWPIWCLOUD_DATA_DIR/files must resolve inside the private data directory.');
    }

    $storageDirectory = rtrim($resolved, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    return $storageDirectory;
}

csrfToken();
