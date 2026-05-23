<?php

declare(strict_types=1);

require_once __DIR__ . DIRECTORY_SEPARATOR . 'auth.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'storage.php';

$currentUser = authRequireLogin();

if (!isset($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token']) || $_SESSION['csrf_token'] === '') {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$csrfToken = $_SESSION['csrf_token'];

$uploadDir = storageUploadDir();


$files = storageListFiles($uploadDir, $currentUser);

function formatFileSize(int $bytes): string
{
    if ($bytes >= 1024 * 1024) {
        return rtrim(rtrim(number_format($bytes / (1024 * 1024), 2, '.', ''), '0'), '.') . ' MB';
    }

    if ($bytes >= 1024) {
        return rtrim(rtrim(number_format($bytes / 1024, 2, '.', ''), '0'), '.') . ' KB';
    }

    return $bytes . ' B';
}

function formatTimestamp(int $timestamp): string
{
    return date('M j, Y H:i', $timestamp);
}

function iniSizeToBytes(string $value): int
{
    $value = trim($value);
    $unit = strtolower(substr($value, -1));
    $number = (int) $value;

    return match ($unit) {
        'g' => $number * 1024 * 1024 * 1024,
        'm' => $number * 1024 * 1024,
        'k' => $number * 1024,
        default => (int) $value,
    };
}

$appUploadLimitBytes = HAWPIWCLOUD_UPLOAD_MAX_BYTES;
$uploadMaxSizeBytes = iniSizeToBytes((string) ini_get('upload_max_filesize'));
$postMaxSizeBytes = iniSizeToBytes((string) ini_get('post_max_size'));

$serverLimits = array_filter([$uploadMaxSizeBytes, $postMaxSizeBytes], static fn(int $bytes): bool => $bytes > 0);
$effectiveServerUploadLimitBytes = $serverLimits === [] ? $appUploadLimitBytes : min($serverLimits);
$effectiveUploadLimitBytes = min($appUploadLimitBytes, $effectiveServerUploadLimitBytes);
$effectiveUploadLimitLabel = formatFileSize($effectiveUploadLimitBytes);

$status = $_GET['status'] ?? '';
$banner = null;
$assetVersion = (string) filemtime(__DIR__ . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'styles.css');

if ($status === 'upload_success') {
    $banner = ['type' => 'success', 'title' => 'Unggahan selesai', 'message' => 'Berkas Anda berhasil ditambahkan ke penyimpanan awan.'];
} elseif ($status === 'delete_success') {
    $banner = ['type' => 'success', 'title' => 'Berkas dihapus', 'message' => 'Berkas yang dipilih berhasil dihapus.'];
} elseif ($status === 'error_permissions') {
    $banner = ['type' => 'error', 'title' => 'Penyimpanan tidak tersedia', 'message' => 'Layanan penyimpanan sedang tidak tersedia sementara. Silakan coba lagi beberapa saat lagi.'];
} elseif ($status === 'error_size') {
    $banner = ['type' => 'error', 'title' => 'Berkas terlalu besar', 'message' => 'Ukuran berkas melebihi batas unggahan saat ini (' . $effectiveUploadLimitLabel . '). Silakan pilih berkas yang lebih kecil lalu coba lagi.'];
} elseif ($status === 'error_server_limit') {
    $banner = ['type' => 'error', 'title' => 'Berkas terlalu besar', 'message' => 'Ukuran berkas melampaui batas unggahan saat ini (' . $effectiveUploadLimitLabel . '). Silakan kompres berkas atau pilih file lain yang lebih kecil.'];
} elseif ($status === 'error_partial') {
    $banner = ['type' => 'error', 'title' => 'Unggahan terputus', 'message' => 'Proses unggahan berkas belum selesai. Silakan coba lagi.'];
} elseif ($status === 'error_nofile') {
    $banner = ['type' => 'error', 'title' => 'Tidak ada berkas dipilih', 'message' => 'Pilih berkas terlebih dahulu sebelum mengirim formulir unggahan.'];
} elseif ($status === 'error_type') {
    $banner = ['type' => 'error', 'title' => 'Jenis berkas tidak didukung', 'message' => 'File yang dipilih tidak sesuai dengan jenis yang diizinkan untuk diunggah.'];
} elseif ($status === 'error_security') {
    $banner = ['type' => 'error', 'title' => 'Permintaan tidak valid', 'message' => 'Sesi atau token keamanan tidak cocok. Silakan muat ulang halaman dan coba lagi.'];
} elseif ($status === 'error_forbidden') {
    $banner = ['type' => 'error', 'title' => 'Akses dibatasi', 'message' => 'Role akun Anda tidak memiliki kewenangan untuk membuka halaman atau menjalankan aksi tersebut.'];
} elseif ($status === 'error') {
    $banner = ['type' => 'error', 'title' => 'Terjadi kesalahan', 'message' => 'Silakan coba lagi dan pastikan berkas yang dipilih sudah benar.'];
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>hawpiwcloud</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    <link rel="stylesheet" href="assets/styles.css?v=<?= htmlspecialchars($assetVersion, ENT_QUOTES, 'UTF-8'); ?>">
</head>

<body>
    <main class="shell">
        <header class="site-header">
            <a class="brand" href="#top" aria-label="Beranda hawpiwcloud">
                <span class="brand-mark" aria-hidden="true">
                    <i class="fa-solid fa-cloud-arrow-up"></i>
                </span>
                <span class="brand-name">hawpiwcloud</span>
            </a>

            <nav class="site-nav" aria-label="Primary">
                <a href="#top">Beranda</a>
                <a href="#files">Berkas</a>
                <a href="#how-it-works">Cara Kerja</a>
                <a href="#faq">FAQ</a>
                <?php if (authCanUseDashboard($currentUser)): ?>
                    <a href="dashboard.php">Dashboard</a>
                <?php endif; ?>
                <a class="action-button nav-cta" href="logout.php">Logout</a>
            </nav>
        </header>

        <section class="hero" id="top">
            <h1><?= !authCanUseDashboard($currentUser) ? 'Viewer Dashboard' : 'Penyimpanan Berbasis Cloud Computing'; ?></h1>
            <p class="subtitle"><?= !authCanUseDashboard($currentUser) ? 'Anda berada di area khusus Viewer. Anda hanya dapat melihat dan mengunduh file yang dibagikan kepada Anda.' : 'Penyimpanan berkas sederhana untuk melihat, mengunduh, dan mengelola file sesuai kewenangan akun yang sedang digunakan.'; ?></p>
        </section>

        <section class="cta-band" id="masuk-section" aria-labelledby="masuk-title">
            <h3 id="masuk-title">Selamat Datang, <?= htmlspecialchars($currentUser['name'], ENT_QUOTES, 'UTF-8'); ?></h3>
            <p>Anda login sebagai level <?= htmlspecialchars(authRoleLabel($currentUser), ENT_QUOTES, 'UTF-8'); ?>. Akses file dan aksi pengelolaan otomatis mengikuti kewenangan akun.</p>
            <div class="entry-actions">
                <?php if (authCanUseDashboard($currentUser)): ?>
                    <a class="primary-button" href="dashboard.php">
                        <i class="button-icon fa-solid fa-cloud-arrow-up" aria-hidden="true"></i>
                        Buka Dashboard
                    </a>
                    <a class="secondary-button" href="dashboard.php#files">
                        <i class="button-icon fa-solid fa-file-lines" aria-hidden="true"></i>
                        Kelola File
                    </a>
                <?php else: ?>
                    <a class="primary-button" href="#files">
                        <i class="button-icon fa-solid fa-file-lines" aria-hidden="true"></i>
                        Lihat Berkas
                    </a>
                    <a class="secondary-button" href="#how-it-works">
                        <i class="button-icon fa-solid fa-circle-info" aria-hidden="true"></i>
                        Cara Kerja
                    </a>
                <?php endif; ?>
                <a class="secondary-button" href="logout.php">
                    <i class="button-icon fa-solid fa-right-from-bracket" aria-hidden="true"></i>
                    Logout
                </a>
            </div>
        </section>

        <div class="stack">
            <?php if ($banner !== null): ?>
                <div class="banner <?= htmlspecialchars($banner['type']); ?>" role="status" aria-live="polite">
                    <div class="banner-badge">
                        <?php if ($banner['type'] === 'success'): ?>
                            <i class="fa-solid fa-circle-check" aria-hidden="true"></i>
                        <?php else: ?>
                            <i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i>
                        <?php endif; ?>
                    </div>
                    <div>
                        <strong><?= htmlspecialchars($banner['title']); ?></strong>
                        <p><?= htmlspecialchars($banner['message']); ?></p>
                    </div>
                </div>
            <?php endif; ?>

            <section class="panel files-panel" aria-labelledby="files-title" id="files">
                <div class="panel-head">
                    <div>
                        <h2 id="files-title">Berkas Cloud</h2>
                        <span><?= count($files); ?> berkas tersedia untuk diunduh</span>
                    </div>
                </div>

                <?php if (count($files) > 0): ?>
                    <div class="table-wrap">
                        <table>
                            <thead>
                                <tr>
                                    <th>Nama Berkas</th>
                                    <th>Ukuran</th>
                                    <th>Terakhir Diubah</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($files as $file): ?>
                                    <tr>
                                        <td data-label="Nama Berkas">
                                            <div class="file-row">
                                                <div class="file-name">
                                                    <?php if ($file['isImage']): ?>
                                                        <img class="file-preview" src="uploads/<?= htmlspecialchars(rawurlencode($file['name']), ENT_QUOTES, 'UTF-8'); ?>" alt="Pratinjau <?= htmlspecialchars($file['name'], ENT_QUOTES, 'UTF-8'); ?>" loading="lazy">
                                                    <?php else: ?>
                                                        <div class="file-icon" aria-hidden="true">
                                                            <i class="fa-solid fa-file-lines"></i>
                                                        </div>
                                                    <?php endif; ?>
                                                    <span title="<?= htmlspecialchars($file['name'], ENT_QUOTES, 'UTF-8'); ?>"><?= htmlspecialchars($file['name'], ENT_QUOTES, 'UTF-8'); ?></span>
                                                </div>
                                                <div class="actions file-actions">
                                                    <a class="action-button download icon-only" href="download.php?file=<?= urlencode($file['name']); ?>" aria-label="Unduh <?= htmlspecialchars($file['name'], ENT_QUOTES, 'UTF-8'); ?>" title="Unduh">
                                                        <i class="fa-solid fa-download" aria-hidden="true"></i>
                                                    </a>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="meta" data-label="Ukuran"><?= formatFileSize((int) $file['size']); ?></td>
                                        <td class="meta" data-label="Terakhir Diubah"><?= formatTimestamp((int) $file['modified']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <div class="empty-card">
                            <div class="empty-mark" aria-hidden="true">
                                <i class="fa-solid fa-cloud-arrow-up"></i>
                            </div>
                            <h3>Belum ada berkas</h3>
                            <p>Cloud masih kosong. File yang diunggah admin dapat dibagikan untuk viewer dan user, sedangkan file user tetap menjadi milik akun tersebut.</p>
                        </div>
                    </div>
                <?php endif; ?>
            </section>

            <section class="how-section" id="how-it-works" aria-labelledby="how-title">
                <div class="how-header">
                    <h2 id="how-title">Cara Kerja</h2>
                    <p>Tiga langkah sederhana untuk membaca, mengunduh, dan menjaga alur pengelolaan berkas tetap rapi.</p>
                </div>

                <div class="steps-grid">
                    <article class="step-card">
                        <div class="step-pill">1</div>
                        <h3>Lihat Berkas Cloud</h3>
                        <p>Setelah login, daftar file yang bisa Anda akses tampil dengan ukuran, waktu perubahan terakhir, dan pratinjau gambar.</p>
                    </article>

                    <article class="step-card">
                        <div class="step-pill">2</div>
                        <h3>Unduh File</h3>
                        <p>Klik ikon unduh pada baris file untuk menyimpan berkas dari cloud ke perangkat Anda tanpa akses untuk mengubah atau menghapusnya.</p>
                    </article>

                    <article class="step-card">
                        <div class="step-pill">3</div>
                        <h3>Kelola dari Dashboard</h3>
                        <p>Pengguna yang memiliki izin upload atau hapus dapat membuka dashboard untuk bekerja di panel khusus pengelolaan file.</p>
                    </article>
                </div>

                <section class="safety-card" aria-labelledby="safety-title">
                    <div class="safety-icon" aria-hidden="true">
                        <i class="fa-solid fa-shield-halved"></i>
                    </div>
                    <div>
                        <h3 id="safety-title">Keamanan Penyimpanan Awan</h3>
                        <p>Setiap unggahan divalidasi sebelum disimpan. Aplikasi memeriksa jenis berkas, ukuran, dan izin role agar penyimpanan tetap stabil dan aman untuk penggunaan sehari-hari.</p>
                        <div class="safety-list">
                            <span>Divalidasi sebelum disimpan</span>
                            <span>Batas dan format dijelaskan di panel upload</span>
                            <span>Batas ukuran ditegakkan</span>
                            <span>Role akses dipisahkan</span>
                        </div>
                    </div>
                </section>

                <div class="cta-band">
                    <h3>Butuh cara yang lebih rapi untuk mengatur berkas?</h3>
                    <p><?= authCanUseDashboard($currentUser) ? 'Gunakan dashboard untuk mengunggah dan mengelola berkas sesuai kewenangan akun Anda.' : 'Login Anda aktif sebagai viewer. Anda dapat melihat dan mengunduh file tertentu yang dibagikan admin.'; ?></p>
                    <a class="cta-button" href="<?= authCanUseDashboard($currentUser) ? 'dashboard.php' : 'logout.php'; ?>"><?= authCanUseDashboard($currentUser) ? 'Buka Dashboard' : 'Logout' ?></a>
                </div>
            </section>

            <section class="faq-section" id="faq" aria-labelledby="faq-title">
                <div class="faq-header">
                    <div class="faq-kicker">Punya pertanyaan?</div>
                    <h2 id="faq-title">Pertanyaan yang Sering Diajukan</h2>
                    <p>Berikut jawaban singkat untuk hal-hal yang paling sering ditanyakan tentang hawpiwcloud dan cara penggunaannya.</p>
                </div>

                <div class="faq-list" data-faq>
                    <article class="faq-item">
                        <button class="faq-question" type="button" aria-expanded="false">
                            <span>Bagaimana cara mengunggah berkas?</span>
                            <span class="faq-icon" aria-hidden="true"><i class="fa-solid fa-plus"></i></span>
                        </button>
                        <div class="faq-answer">
                            <div class="faq-answer-inner">Unggah berkas tersedia untuk admin dan user melalui dashboard. File yang diunggah user otomatis menjadi milik akun tersebut.</div>
                        </div>
                    </article>

                    <article class="faq-item">
                        <button class="faq-question" type="button" aria-expanded="false">
                            <span>Format berkas apa yang didukung?</span>
                            <span class="faq-icon" aria-hidden="true"><i class="fa-solid fa-plus"></i></span>
                        </button>
                        <div class="faq-answer">
                            <div class="faq-answer-inner">Format berkas yang didukung ditampilkan langsung di panel upload dashboard agar informasinya berada dekat dengan tempat pengguna memilih file.</div>
                        </div>
                    </article>

                    <article class="faq-item">
                        <button class="faq-question" type="button" aria-expanded="false">
                            <span>Apakah saya bisa melihat pratinjau sebelum mengunggah?</span>
                            <span class="faq-icon" aria-hidden="true"><i class="fa-solid fa-plus"></i></span>
                        </button>
                        <div class="faq-answer">
                            <div class="faq-answer-inner">Admin dapat melihat pratinjau sebelum mengunggah dari dashboard. Di halaman user, gambar yang sudah tersimpan tetap tampil sebagai thumbnail pada daftar berkas.</div>
                        </div>
                    </article>

                    <article class="faq-item">
                        <button class="faq-question" type="button" aria-expanded="false">
                            <span>Berapa batas ukuran unggahan?</span>
                            <span class="faq-icon" aria-hidden="true"><i class="fa-solid fa-plus"></i></span>
                        </button>
                        <div class="faq-answer">
                            <div class="faq-answer-inner">Batas unggahan saat ini adalah <?= htmlspecialchars($effectiveUploadLimitLabel); ?> per berkas. Jika berkas Anda lebih besar, silakan kompres terlebih dahulu atau unggah file yang ukurannya lebih kecil.</div>
                        </div>
                    </article>

                    <article class="faq-item">
                        <button class="faq-question" type="button" aria-expanded="false">
                            <span>Apakah berkas saya aman?</span>
                            <span class="faq-icon" aria-hidden="true"><i class="fa-solid fa-plus"></i></span>
                        </button>
                        <div class="faq-answer">
                            <div class="faq-answer-inner">Setiap unggahan divalidasi sebelum disimpan. Sistem mengecek izin folder, ukuran berkas, dan proses unggah agar tetap stabil dan aman digunakan.</div>
                        </div>
                    </article>

                    <article class="faq-item">
                        <button class="faq-question" type="button" aria-expanded="false">
                            <span>Bagaimana cara menghapus berkas?</span>
                            <span class="faq-icon" aria-hidden="true"><i class="fa-solid fa-plus"></i></span>
                        </button>
                        <div class="faq-answer">
                            <div class="faq-answer-inner">Admin dapat menghapus semua file dari dashboard. User hanya dapat menghapus file yang dia unggah sendiri.</div>
                        </div>
                    </article>
                </div>

                <p class="faq-footnote">Jika pertanyaan Anda belum terjawab, cek daftar berkas cloud atau hubungi admin untuk penambahan dan penghapusan file.</p>
            </section>

            <footer class="site-footer" aria-labelledby="footer-brand-title">
                <div class="footer-inner">
                    <div class="footer-top">
                        <div class="footer-brand-block">
                            <h2 class="footer-brand-name" id="footer-brand-title">hawpiwcloud</h2>
                            <p class="footer-copy">Platform penyimpanan berkas sederhana untuk mengunggah, meninjau, mengunduh, dan mengelola file dengan tampilan yang bersih dan cepat dipahami.</p>

                            <div class="footer-signup" aria-label="Langganan pembaruan">
                                <form action="#" method="post" onsubmit="return false;">
                                    <input type="email" name="email" placeholder="Masukkan email Anda" aria-label="Masukkan email Anda">
                                    <button type="button">Gabung</button>
                                </form>
                            </div>
                        </div>

                        <div class="footer-columns" aria-label="Tautan footer">
                            <div class="footer-column">
                                <h4>Produk</h4>
                                <div class="footer-links">
                                    <a href="#how-it-works">Cara Kerja</a>
                                    <?php if (authCanUseDashboard($currentUser)): ?>
                                        <a href="dashboard.php">Dashboard</a>
                                    <?php endif; ?>
                                    <a href="#faq">FAQ</a>
                                </div>
                            </div>

                            <div class="footer-column">
                                <h4>Perusahaan</h4>
                                <div class="footer-links">
                                    <a href="#top">Tentang</a>
                                    <a href="#how-it-works">Blog</a>
                                    <?php if (authCanUseDashboard($currentUser)): ?>
                                        <a href="dashboard.php">Dashboard</a>
                                    <?php endif; ?>
                                    <a href="#faq">Kontak</a>
                                </div>
                            </div>

                            <div class="footer-column">
                                <h4>Sumber Daya</h4>
                                <div class="footer-links">
                                    <a href="#how-it-works">Panduan</a>
                                    <?php if (authCanUseDashboard($currentUser)): ?>
                                        <a href="dashboard.php">Lihat Dashboard</a>
                                    <?php endif; ?>
                                    <a href="#faq">Panduan Berkas</a>
                                    <a href="#faq">Bantuan</a>
                                </div>
                            </div>

                            <div class="footer-column">
                                <h4>Legal</h4>
                                <div class="footer-links">
                                    <a href="#">Kebijakan Privasi</a>
                                    <a href="#">Ketentuan Layanan</a>
                                    <a href="#">Kebijakan Cookie</a>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="footer-divider" aria-hidden="true"></div>

                    <div class="footer-bottom">
                        <div class="footer-social" aria-label="Sosial media">
                            <a class="social-link" href="#" aria-label="X">
                                <i class="fa-brands fa-x-twitter" aria-hidden="true"></i>
                            </a>
                            <a class="social-link" href="#" aria-label="LinkedIn">
                                <i class="fa-brands fa-linkedin-in" aria-hidden="true"></i>
                            </a>
                        </div>

                        <div class="footer-bottom-copy">© 2026 hawpiwcloud. Hak cipta dilindungi.</div>

                        <div class="footer-policy-links">
                            <a href="#">Kebijakan Privasi</a>
                            <a href="#">Ketentuan Layanan</a>
                            <a href="#">Kebijakan Cookie</a>
                        </div>
                    </div>
                </div>
            </footer>
        </div>
    </main>
    <script src="assets/app.js" defer></script>
</body>

</html>
