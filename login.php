<?php

declare(strict_types=1);

require_once __DIR__ . DIRECTORY_SEPARATOR . 'auth.php';

authStartSession();

$currentUser = authCurrentUser();
if ($currentUser !== null) {
    header('Location: ' . authHomeForUser($currentUser));
    exit;
}

$status = $_GET['status'] ?? '';
$errorMessage = '';
$assetVersion = (string) filemtime(__DIR__ . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'styles.css');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim((string) ($_POST['username'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');

    if (authLogin($username, $password)) {
        $user = authCurrentUser();
        header('Location: ' . authHomeForUser($user ?? ['role' => 'user']));
        exit;
    }

    $status = 'invalid';
    $errorMessage = 'Username atau password tidak sesuai.';
} elseif ($status === 'login_required') {
    $errorMessage = 'Silakan login terlebih dahulu untuk masuk ke hawpiwcloud.';
} elseif ($status === 'logged_out') {
    $errorMessage = 'Anda sudah keluar dari akun.';
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login hawpiwcloud</title>
    <link rel="stylesheet" href="assets/styles.css?v=<?= htmlspecialchars($assetVersion, ENT_QUOTES, 'UTF-8'); ?>">
</head>

<body class="auth-body">
    <main class="auth-page">
        <header class="auth-nav">
            <a class="auth-logo" href="login.php" aria-label="Login hawpiwcloud">
            </a>

            <nav class="auth-menu" aria-label="Login navigation">
                <a href="guest.php">Guest</a>
            </nav>
        </header>

        <section class="auth-hero" aria-labelledby="login-title">
            <div class="auth-copy">
                <h1 id="login-title">
                    Masuk ke
                    <span>hawpiw</span>
                    cloud
                </h1>
                <p>Akses file tersimpan sesuai level pengguna. Pilih akun demo di bawah, lalu login untuk masuk ke halaman yang sesuai.</p>

                <?php if ($errorMessage !== ''): ?>
                    <div class="auth-alert <?= $status === 'logged_out' ? 'success' : 'error'; ?>" role="status" aria-live="polite">
                        <strong><?= $status === 'logged_out' ? 'Logout berhasil' : ($status === 'invalid' ? 'Login gagal' : 'Login diperlukan'); ?></strong>
                        <span><?= htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8'); ?></span>
                    </div>
                <?php endif; ?>

                <form class="auth-form" action="login.php" method="post" id="login-form">
                    <label>
                        <span>Username</span>
                        <input type="text" name="username" autocomplete="username" placeholder="admin, user, atau viewer" required>
                    </label>

                    <label>
                        <span>Password</span>
                        <input type="password" name="password" autocomplete="current-password" placeholder="Masukkan password" required>
                    </label>

                    <button class="auth-submit" type="submit">Login ke hawpiwcloud</button>
                </form>

                
                <a class="auth-guest-link" href="guest.php">Masuk sebagai Guest</a>
                <div class="credential-grid" id="accounts" aria-label="Akun demo dan kewenangan">
                    <article>
                        <span>Admin</span>
                        <strong>admin / admin123</strong>
                        <p>Mengelola semua pengguna dan semua file.</p>
                    </article>

                    <article>
                        <span>User</span>
                        <strong>user / user123</strong>
                        <p>Mengunggah, mengunduh, dan menghapus file miliknya sendiri.</p>
                    </article>

                    <article>
                        <span>Viewer</span>
                        <strong>viewer / viewer123</strong>
                        <p>Melihat dan mengunduh file tertentu yang dibagikan.</p>
                    </article>
                </div>
            </div>

            <div class="auth-visual" aria-hidden="true">
                <div class="visual-glow"></div>
                <svg class="cloud-illustration" viewBox="0 0 760 620" fill="none">
                    <ellipse cx="408" cy="548" rx="246" ry="34" fill="#11142B" opacity="0.55" />

                    <path d="M250 210c14-72 76-122 152-122 58 0 109 30 138 75 67 5 120 62 120 131 0 73-59 132-132 132H254c-64 0-116-52-116-116 0-53 35-98 85-111 9-3 18-4 27-4v15Z" fill="#DCE4FF" />
                    <path d="M268 236c11-60 62-102 126-102 49 0 92 25 116 63 58 4 103 52 103 111 0 61-49 110-110 110H256c-55 0-100-45-100-100 0-46 32-86 76-96 12-3 24-3 36-1v15Z" fill="#AEB9E0" />
                    <path d="M284 260c9-45 47-76 94-76 36 0 67 18 85 47 43 3 77 39 77 82 0 45-37 82-82 82H278c-41 0-74-33-74-74 0-34 23-63 56-72 8-2 16-2 24-1v12Z" fill="#303760" opacity="0.72" />

                    <rect x="135" y="345" width="190" height="118" rx="24" fill="#171B38" stroke="#333A68" stroke-width="8" />
                    <rect x="160" y="374" width="139" height="18" rx="9" fill="#394274" />
                    <rect x="160" y="413" width="98" height="18" rx="9" fill="#394274" />
                    <circle cx="284" cy="422" r="10" fill="#67E8F9" />
                    <circle cx="257" cy="422" r="10" fill="#A78BFA" />
                    <path d="M202 326v-37c0-31 25-56 56-56h110" stroke="#7C5CFF" stroke-width="12" stroke-linecap="round" />
                    <path d="m344 214 29 20-29 20" stroke="#7C5CFF" stroke-width="12" stroke-linecap="round" stroke-linejoin="round" />

                    <rect x="481" y="342" width="142" height="154" rx="26" fill="#171B38" stroke="#333A68" stroke-width="8" />
                    <path d="M511 385h78M511 424h52M511 463h78" stroke="#55609A" stroke-width="12" stroke-linecap="round" />
                    <circle cx="586" cy="424" r="11" fill="#34D399" />
                    <circle cx="611" cy="424" r="11" fill="#A78BFA" />
                    <path d="M556 321v-36c0-32-26-58-58-58h-86" stroke="#29C7F6" stroke-width="12" stroke-linecap="round" />
                    <path d="m435 207-29 20 29 20" stroke="#29C7F6" stroke-width="12" stroke-linecap="round" stroke-linejoin="round" />

                    <rect x="308" y="230" width="172" height="184" rx="48" fill="#F2F5FF" />
                    <rect x="331" y="254" width="126" height="84" rx="32" fill="#11162D" />
                    <circle cx="372" cy="294" r="14" fill="#25C7F5" />
                    <circle cx="416" cy="294" r="14" fill="#25C7F5" />
                    <path d="M374 326h48" stroke="#25C7F5" stroke-width="10" stroke-linecap="round" />
                    <rect x="352" y="365" width="84" height="38" rx="19" fill="#7C5CFF" />
                    <path d="M378 384h32" stroke="#EDE9FE" stroke-width="8" stroke-linecap="round" />
                    <path d="M309 310c-40 12-68 47-68 89M478 310c42 11 72 47 72 89" stroke="#C8D1EF" stroke-width="28" stroke-linecap="round" />
                    <path d="M342 414l-22 66M445 414l22 66" stroke="#C8D1EF" stroke-width="28" stroke-linecap="round" />
                    <rect x="292" y="478" width="74" height="48" rx="20" fill="#AEB9E0" />
                    <rect x="422" y="478" width="74" height="48" rx="20" fill="#AEB9E0" />
                    <circle cx="336" cy="353" r="9" fill="#34D399" />
                    <circle cx="394" cy="353" r="9" fill="#94A3B8" />
                    <circle cx="451" cy="353" r="9" fill="#94A3B8" />

                    <rect x="148" y="147" width="118" height="92" rx="24" fill="#242B55" stroke="#4A5590" stroke-width="7" />
                    <path d="M180 184h56M180 209h35" stroke="#9AA7E8" stroke-width="10" stroke-linecap="round" />
                    <path d="M239 204l20 20 33-48" stroke="#FACC15" stroke-width="11" stroke-linecap="round" stroke-linejoin="round" />

                    <rect x="528" y="138" width="112" height="112" rx="28" fill="#242B55" stroke="#4A5590" stroke-width="7" />
                    <path d="M584 169v50M559 194h50" stroke="#67E8F9" stroke-width="12" stroke-linecap="round" />
                    <path d="M536 118h39M631 266h39" stroke="#7C5CFF" stroke-width="10" stroke-linecap="round" />

                    <path d="M390 96c18 0 33-15 33-33M390 96c-18 0-33-15-33-33M390 96v61" stroke="#FACC15" stroke-width="12" stroke-linecap="round" />
                    <circle cx="357" cy="63" r="15" fill="#FACC15" />
                    <circle cx="423" cy="63" r="15" fill="#FACC15" />
                </svg>
            </div>
        </section>
    </main>
</body>

</html>
