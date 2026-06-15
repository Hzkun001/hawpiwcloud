<?php

declare(strict_types=1);

require_once __DIR__ . DIRECTORY_SEPARATOR . 'auth.php';

$currentUser = authRequireAdmin();

function redirectWithUserStatus(string $status): void
{
    header('Location: dashboard-users.php?status=' . rawurlencode($status));
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

    authLogAudit('admin.user_create', $result === 'success' ? 'success' : 'failed', $currentUser, [
        'target' => (string) ($_POST['username'] ?? ''),
        'role' => (string) ($_POST['role'] ?? ''),
        'result' => $result,
    ]);

    redirectWithUserStatus('user_create_' . $result);
}

if ($action === 'delete') {
    $username = (string) ($_POST['username'] ?? '');
    if ($username === $currentUser['username']) {
        redirectWithUserStatus('user_delete_self');
    }

    $result = authDeleteAccount($username);
    authLogAudit('admin.user_delete', $result === 'success' ? 'success' : 'failed', $currentUser, [
        'target' => $username,
        'result' => $result,
    ]);

    redirectWithUserStatus('user_delete_' . $result);
}

redirectWithUserStatus('error');
