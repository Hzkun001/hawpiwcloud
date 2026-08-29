<?php
declare(strict_types=1);

require_once __DIR__ . DIRECTORY_SEPARATOR . 'bootstrap.php';

const LOGIN_LIMIT = 5;
const LOGIN_WINDOW = 900;

function loginThrottle(string $remoteAddress, string $action): int
{
    // ponytail: single-host file lock; use a shared/edge limiter for multi-node deployments.
    $path = privateDataDirectory() . DIRECTORY_SEPARATOR . '.login-attempts.json';
    $isNew = !file_exists($path);
    $handle = fopen($path, 'c+');

    if ($handle === false || !flock($handle, LOCK_EX)) {
        configurationError('Login throttle state cannot be locked.');
    }

    if ($isNew) {
        chmod($path, 0600);
    }

    rewind($handle);
    $contents = stream_get_contents($handle);
    $state = $contents === '' ? [] : json_decode($contents, true);
    if (!is_array($state)) {
        configurationError('Login throttle state is invalid.');
    }

    $now = time();
    $cutoff = $now - LOGIN_WINDOW;
    foreach ($state as $key => $timestamps) {
        if (!is_array($timestamps)) {
            unset($state[$key]);
            continue;
        }

        $state[$key] = array_values(array_filter(
            $timestamps,
            static fn(mixed $timestamp): bool => is_int($timestamp) && $timestamp > $cutoff
        ));
        if ($state[$key] === []) {
            unset($state[$key]);
        }
    }

    $key = hash('sha256', $remoteAddress);
    if ($action === 'failure') {
        $state[$key][] = $now;
    } elseif ($action === 'success') {
        unset($state[$key]);
    }

    $attempts = $state[$key] ?? [];
    $retryAfter = count($attempts) >= LOGIN_LIMIT ? LOGIN_WINDOW - ($now - min($attempts)) : 0;

    rewind($handle);
    if (!ftruncate($handle, 0) || fwrite($handle, json_encode($state, JSON_THROW_ON_ERROR)) === false) {
        configurationError('Login throttle state cannot be written.');
    }
    fflush($handle);
    flock($handle, LOCK_UN);
    fclose($handle);

    return max(0, $retryAfter);
}

function logout(): never
{
    clearAuthentication();
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }

    session_destroy();
    redirectToLogin();
}

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'logout') {
    requireAuthentication();
    if (!isValidCsrfToken($_POST['csrf_token'] ?? null)) {
        redirectWithStatus('error_security');
    }
    logout();
}

if (isAuthenticated()) {
    header('Location: index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isValidCsrfToken($_POST['csrf_token'] ?? null)) {
        http_response_code(403);
        $error = 'Permintaan tidak valid. Muat ulang halaman dan coba lagi.';
    } else {
        $remoteAddress = (string)($_SERVER['REMOTE_ADDR'] ?? 'unknown');
        $retryAfter = loginThrottle($remoteAddress, 'check');

        if ($retryAfter > 0) {
            http_response_code(429);
            header('Retry-After: ' . $retryAfter);
            $error = 'Terlalu banyak percobaan. Coba lagi nanti.';
        } elseif (password_verify((string)($_POST['password'] ?? ''), configuredPasswordHash())) {
            loginThrottle($remoteAddress, 'success');
            session_regenerate_id();
            $_SESSION['authenticated'] = true;
            $_SESSION['last_activity'] = time();
            header('Location: index.php');
            exit;
        } else {
            $retryAfter = loginThrottle($remoteAddress, 'failure');
            http_response_code($retryAfter > 0 ? 429 : 401);
            if ($retryAfter > 0) {
                header('Retry-After: ' . $retryAfter);
            }
            $error = $retryAfter > 0
                ? 'Terlalu banyak percobaan. Coba lagi nanti.'
                : 'Kata sandi tidak valid.';
        }
    }
}

$csrfToken = csrfToken();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Masuk — hawpiwcloud</title>
    <link rel="stylesheet" href="assets/styles.css">
</head>
<body>
    <main class="login-shell">
        <a class="brand" href="login.php" aria-label="hawpiwcloud">
            <span class="brand-mark" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none">
                    <path d="M12 4v11" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                    <path d="m7.5 9 4.5-4.5L16.5 9" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    <path d="M5.5 15.5V17A2.5 2.5 0 0 0 8 19.5h8A2.5 2.5 0 0 0 18.5 17v-1.5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </span>
            <span class="brand-name">hawpiwcloud</span>
        </a>

        <section class="panel login-panel" aria-labelledby="login-title">
            <div class="panel-head">
                <div>
                    <h1 id="login-title">Masuk</h1>
                    <span>Gunakan kata sandi ruang penyimpanan Anda.</span>
                </div>
            </div>

            <form class="login-form" method="post" action="login.php">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                <?php if ($error !== null): ?>
                    <div class="login-error" role="alert"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
                <?php endif; ?>
                <label for="password">Kata sandi</label>
                <input id="password" name="password" type="password" autocomplete="current-password" required autofocus>
                <button class="primary-button" type="submit">Masuk</button>
            </form>
        </section>
    </main>
</body>
</html>
