<?php

declare(strict_types=1);

const HAWPIWCLOUD_ACCOUNTS = [
    'user' => [
        'password' => 'user123',
        'role' => 'user',
        'name' => 'User Biasa',
    ],
    'admin' => [
        'password' => 'admin123',
        'role' => 'admin',
        'name' => 'Administrator',
    ],
];

function authStartSession(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
}

function authCurrentUser(): ?array
{
    authStartSession();

    $accounts = HAWPIWCLOUD_ACCOUNTS;
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

function authLogin(string $username, string $password): bool
{
    authStartSession();

    $accounts = HAWPIWCLOUD_ACCOUNTS;
    if (!isset($accounts[$username])) {
        return false;
    }

    $account = $accounts[$username];
    if (!hash_equals($account['password'], $password)) {
        return false;
    }

    session_regenerate_id(true);
    $_SESSION['auth_username'] = $username;

    return true;
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
        header('Location: login.php?status=login_required');
        exit;
    }

    return $user;
}

function authRequireAdmin(): array
{
    $user = authRequireLogin();
    if ($user['role'] !== 'admin') {
        header('Location: index.php?status=error_forbidden');
        exit;
    }

    return $user;
}

function authHomeForUser(array $user): string
{
    return $user['role'] === 'admin' ? 'dashboard.php' : 'index.php';
}
