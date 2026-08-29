<?php

declare(strict_types=1);

const HAWPIWCLOUD_ACCOUNTS = [
    'admin' => [
        'password' => 'admin123',
        'role' => 'admin',
        'name' => 'Administrator',
    ],
    'user' => [
        'password' => 'user123',
        'role' => 'user',
        'name' => 'User Biasa',
    ],
    'viewer' => [
        'password' => 'viewer123',
        'role' => 'viewer',
        'name' => 'Viewer',
    ],
];

const HAWPIWCLOUD_ROLE_LABELS = [
    'admin' => 'Admin',
    'user' => 'User',
    'viewer' => 'Viewer',
];

const HAWPIWCLOUD_ROLE_DESCRIPTIONS = [
    'admin' => 'Mengelola semua pengguna dan semua file.',
    'user' => 'Mengunggah, mengunduh, dan menghapus file miliknya sendiri.',
    'viewer' => 'Hanya melihat dan mengunduh file tertentu dari admin.',
];

function authStartSession(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
}

function authUsersPath(): string
{
    return __DIR__ . DIRECTORY_SEPARATOR . 'users.json';
}

function authStoredAccounts(): array
{
    $path = authUsersPath();
    if (!is_file($path)) {
        return [];
    }

    $raw = file_get_contents($path);
    if (!is_string($raw) || $raw === '') {
        return [];
    }

    $decoded = json_decode($raw, true);

    return is_array($decoded) ? $decoded : [];
}

function authWriteStoredAccounts(array $accounts): bool
{
    $encoded = json_encode($accounts, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    if (!is_string($encoded)) {
        return false;
    }

    return file_put_contents(authUsersPath(), $encoded . PHP_EOL, LOCK_EX) !== false;
}

function authAccounts(): array
{
    return array_replace(HAWPIWCLOUD_ACCOUNTS, authStoredAccounts());
}

function authIsValidUsername(string $username): bool
{
    return (bool) preg_match('/^[A-Za-z0-9_-]{3,32}$/', $username);
}

function authIsValidRole(string $role): bool
{
    return in_array($role, ['admin', 'user', 'viewer'], true);
}

function authRoleDescription(string $role): string
{
    return HAWPIWCLOUD_ROLE_DESCRIPTIONS[$role] ?? 'Akses terbatas atau hanya melihat halaman tertentu.';
}

function authCreateAccount(string $username, string $password, string $name, string $role): string
{
    $username = trim($username);
    $name = trim($name);

    if (!authIsValidUsername($username)) {
        return 'invalid_username';
    }

    if (strlen($password) < 6) {
        return 'invalid_password';
    }

    if (!authIsValidRole($role)) {
        return 'invalid_role';
    }

    $accounts = authAccounts();
    if (isset($accounts[$username])) {
        return 'duplicate';
    }

    $storedAccounts = authStoredAccounts();
    $storedAccounts[$username] = [
        'passwordHash' => password_hash($password, PASSWORD_DEFAULT),
        'role' => $role,
        'name' => $name !== '' ? $name : $username,
        'createdAt' => date(DATE_ATOM),
    ];

    return authWriteStoredAccounts($storedAccounts) ? 'success' : 'error';
}

function authDeleteAccount(string $username): string
{
    $username = trim($username);
    if ($username === 'admin') {
        return 'protected';
    }

    $storedAccounts = authStoredAccounts();
    if (!isset($storedAccounts[$username])) {
        return 'missing';
    }

    unset($storedAccounts[$username]);

    return authWriteStoredAccounts($storedAccounts) ? 'success' : 'error';
}

function authCurrentUser(): ?array
{
    authStartSession();

    $accounts = authAccounts();
    $username = $_SESSION['auth_username'] ?? null;
    if (!is_string($username) || !isset($accounts[$username])) {
        return null;
    }

    return [
        'username' => $username,
        'name' => $accounts[$username]['name'],
        'role' => $accounts[$username]['role'],
    ];
}

function authIsAdmin(): bool
{
    $user = authCurrentUser();

    return $user !== null && $user['role'] === 'admin';
}

function authRoleLabel(?array $user): string
{
    $role = $user['role'] ?? 'guest';

    return HAWPIWCLOUD_ROLE_LABELS[$role] ?? 'Tamu';
}

function authCanUseDashboard(?array $user): bool
{
    return $user !== null && in_array($user['role'], ['admin', 'user'], true);
}

function authCanUpload(?array $user): bool
{
    return $user !== null && in_array($user['role'], ['admin', 'user'], true);
}

function authCanViewFile(?array $user, array $file): bool
{
    if ($user['role'] === 'admin') {
        return true;
    }

    if ($user['role'] === 'user') {
        return ($file['owner'] ?? '') === $user['username'];
    }

    if ($user['role'] === 'viewer') {
        return (bool) ($file['viewerAccess'] ?? false);
    }

    return false;
}

function authCanDownloadFile(?array $user, array $file): bool
{
    if ($user === null) {
        return false;
    }

    return authCanViewFile($user, $file);
}

function authCanDeleteFile(?array $user, array $file): bool
{
    if ($user === null) {
        return false;
    }

    if ($user['role'] === 'admin') {
        return true;
    }

    return $user['role'] === 'user' && ($file['owner'] ?? '') === $user['username'];
}

function authLogin(string $username, string $password): bool
{
    authStartSession();

    $accounts = authAccounts();
    if (!isset($accounts[$username])) {
        return false;
    }

    $account = $accounts[$username];
    $isValidPassword = false;
    if (isset($account['passwordHash']) && is_string($account['passwordHash'])) {
        $isValidPassword = password_verify($password, $account['passwordHash']);
    } elseif (isset($account['password']) && is_string($account['password'])) {
        $isValidPassword = hash_equals($account['password'], $password);
    }

    if (!$isValidPassword) {
        return false;
    }

    session_regenerate_id(true);
    $_SESSION['auth_username'] = $username;
    $_SESSION['auth_last_login_at'] = date(DATE_ATOM);

    return true;
}

function authAuditLogger(): ?BackupLogger
{
    $bootstrap = __DIR__ . DIRECTORY_SEPARATOR . 'backup' . DIRECTORY_SEPARATOR . 'bootstrap.php';
    if (!is_file($bootstrap)) {
        return null;
    }

    try {
        require_once $bootstrap;

        return new BackupLogger(BackupConfig::fromEnvironment(__DIR__));
    } catch (Throwable) {
        return null;
    }
}

function authLogAudit(string $operation, string $status, ?array $user = null, array $context = []): void
{
    $logger = authAuditLogger();
    if ($logger === null) {
        return;
    }

    try {
        $logger->audit(
            $operation,
            $status,
            (string) ($user['username'] ?? 'guest'),
            (string) ($_SERVER['REMOTE_ADDR'] ?? 'web'),
            $context
        );
    } catch (Throwable) {
        // Audit logging must not make authentication unavailable.
    }
}

function authLogout(): void
{
    authStartSession();
    unset($_SESSION['auth_username']);
    session_regenerate_id(true);
}

function authRequireLogin(): array
{
    $user = authCurrentUser();
    if ($user === null) {
        authLogAudit('access.login_required', 'denied', null, ['path' => (string) ($_SERVER['REQUEST_URI'] ?? '')]);
        header('Location: login.php?status=login_required');
        exit;
    }

    return $user;
}

function authRequireAdmin(): array
{
    $user = authRequireLogin();
    if ($user['role'] !== 'admin') {
        authLogAudit('access.admin_page', 'denied', $user, ['path' => (string) ($_SERVER['REQUEST_URI'] ?? '')]);
        header('Location: index.php?status=error_forbidden');
        exit;
    }

    return $user;
}

function authHomeForUser(array $user): string
{
    return authCanUseDashboard($user) ? 'dashboard.php' : 'index.php';
}
