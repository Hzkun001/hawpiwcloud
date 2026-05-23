<?php

declare(strict_types=1);

require_once __DIR__ . DIRECTORY_SEPARATOR . 'auth.php';

$currentUser = authRequireAdmin();

function redirectWithUserStatus(string $status): void
{
    header('Location: dashboard.php?status=' . rawurlencode($status) . '#users');
    exit;
}

function isValidCsrfToken(?string $token): bool
{
    return isset($_SESSION['csrf_token']) && is_string($_SESSION['csrf_token']) && $token !== null && hash_equals($_SESSION['csrf_token'], $token);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirectWithUserStatus('error');
}

if (!isValidCsrfToken($_POST['csrf_token'] ?? null)) {
    redirectWithUserStatus('error_security');
}

$action = (string) ($_POST['action'] ?? '');

if ($action === 'create') {
    $result = authCreateAccount(
        (string) ($_POST['username'] ?? ''),
        (string) ($_POST['password'] ?? ''),
        (string) ($_POST['name'] ?? ''),
        (string) ($_POST['role'] ?? '')
    );

    redirectWithUserStatus('user_create_' . $result);
}

if ($action === 'delete') {
    $username = (string) ($_POST['username'] ?? '');
    if ($username === $currentUser['username']) {
        redirectWithUserStatus('user_delete_self');
    }

    redirectWithUserStatus('user_delete_' . authDeleteAccount($username));
}

redirectWithUserStatus('error');
