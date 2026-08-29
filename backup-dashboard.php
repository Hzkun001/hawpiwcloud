<?php

declare(strict_types=1);

require_once __DIR__ . DIRECTORY_SEPARATOR . 'auth.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'dashboard-layout.php';

authStartSession();

if (!isset($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token']) || $_SESSION['csrf_token'] === '') {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$csrfToken = $_SESSION['csrf_token'];
$flash = isset($_SESSION['backup_flash']) && is_array($_SESSION['backup_flash']) ? $_SESSION['backup_flash'] : null;
unset($_SESSION['backup_flash']);

try {
    $currentUser = authRequireAdmin();
} catch (Throwable $exception) {
    error_log('hawpiwcloud backup dashboard auth error: ' . $exception->getMessage());
    header('Location: dashboard.php?status=error_forbidden');
    exit;
}

$context = [
    'currentUser' => $currentUser,
    'assetVersion' => (string) filemtime(__DIR__ . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'css' . DIRECTORY_SEPARATOR . 'dashboard.css'),
];

$backupBootstrap = __DIR__ . DIRECTORY_SEPARATOR . 'backup' . DIRECTORY_SEPARATOR . 'bootstrap.php';
$backupCssPath = __DIR__ . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'css' . DIRECTORY_SEPARATOR . 'backup-dashboard.css';
$backupCssVersion = is_file($backupCssPath) ? (string) filemtime($backupCssPath) : '1';
$backupDownloadAction = 'backup-download.php';
$backupBootstrapVersion = 'unknown';

function backupFormatBytes(int $bytes): string
{
    if ($bytes >= 1024 * 1024 * 1024) {
        return number_format($bytes / (1024 * 1024 * 1024), 2) . ' GB';
    }

    if ($bytes >= 1024 * 1024) {
        return number_format($bytes / (1024 * 1024), 2) . ' MB';
    }

    return number_format($bytes / 1024, 2) . ' KB';
}

function backupRenderDownloadButton(string $label, string $target, array $params): void
{
    $query = http_build_query($params, '', '&', PHP_QUERY_RFC3986);
    $url = $target . ($query !== '' ? '?' . $query : '');
    ?>
    <form action="<?= htmlspecialchars($url, ENT_QUOTES, 'UTF-8'); ?>" method="get" class="backup-inline-action">
        <button class="button button--secondary" type="submit"><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?></button>
    </form>
    <?php
}

function backupSuccessfulOffsiteCount(array $offsite): int
{
    $names = [];
    foreach ($offsite as $result) {
        if (!is_array($result) || ($result['status'] ?? '') !== 'success') {
            continue;
        }
        $names[] = (string) ($result['name'] ?? '');
    }

    return count(array_unique(array_filter($names, 'strlen')));
}

$points = [];
$statistics = ['total' => 0, 'bytes' => 0];
$lastStatus = 'unavailable';
$lastTime = 'Belum tersedia';
$versions = [];
$auditRows = [];
$backupNotice = null;
$backupDebug = null;
$backupMissing = !is_file($backupBootstrap);
$backupModeLabel = 'Standard';

if (!$backupMissing) {
    try {
        require_once $backupBootstrap;
        if (defined('HAWPIWCLOUD_BACKUP_BOOTSTRAP_VERSION')) {
            $backupBootstrapVersion = (string) HAWPIWCLOUD_BACKUP_BOOTSTRAP_VERSION;
        }
        $config = BackupConfig::fromEnvironment(__DIR__);
        $manager = new BackupManager($config);
        $logger = new BackupLogger($config);
        $backupModeLabel = $config->webFriendlyMode() ? 'Web-Friendly' : 'Standard';

        if (!$config->shellAvailable() && !$config->webFriendlyMode()) {
            $backupNotice = 'Server membatasi proses eksternal. Backup tetap tersedia, tetapi gunakan konfigurasi ZIP-only dengan BACKUP_DATABASE_DRIVER=json, BACKUP_OFFSITE_DRIVER=local, dan tanpa enkripsi untuk mode web-friendly.';
        } elseif (!$config->shellAvailable()) {
            $backupNotice = 'Server membatasi proses eksternal. Dashboard tetap dapat dipakai dengan mode ZIP-only dan database JSON.';
        }

        $points = $manager->listRestorePoints();
        $statistics = $manager->statistics();
        $status = $logger->readStatus();
        $versions = (new FileVersionManager($config))->list();

        if (is_file($config->auditPath)) {
            $lines = file($config->auditPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
            foreach (array_slice(array_reverse($lines), 0, 30) as $line) {
                $decoded = json_decode($line, true);
                if (is_array($decoded)) {
                    $auditRows[] = $decoded;
                }
            }
        }

        $lastStatus = (string) ($status['last_backup']['status'] ?? 'never_run');
        $lastTime = (string) ($status['last_backup']['time'] ?? 'Belum pernah');
    } catch (Throwable $exception) {
        error_log('hawpiwcloud backup dashboard error: ' . $exception->getMessage());
        $backupDebug = $exception->getMessage();
        $backupMissing = true;
    }
}

dashboardRenderLayoutStart($context, 'backup', 'Status Backup', 'Restore point, backup incremental, verifikasi integritas, retention, dan audit');
?>
<link rel="stylesheet" href="assets/css/backup-dashboard.css?v=<?= htmlspecialchars($backupCssVersion, ENT_QUOTES, 'UTF-8'); ?>">

<?php if ($flash !== null): ?>
    <div class="flash flash--<?= htmlspecialchars((string) ($flash['type'] ?? 'error')); ?>"><?= htmlspecialchars((string) ($flash['message'] ?? '')); ?></div>
<?php endif; ?>

<?php if ($backupNotice !== null): ?>
    <section class="card backup-panel">
        <h2>Mode Hosting Terbatas</h2>
        <p class="text-muted"><?= htmlspecialchars($backupNotice, ENT_QUOTES, 'UTF-8'); ?></p>
    </section>
<?php endif; ?>

<?php if ($backupMissing): ?>
    <section class="card backup-panel">
        <h2>Backup belum dapat dijalankan</h2>
        <p class="text-muted">Modul backup belum lengkap atau gagal dimuat di server ini. Periksa file `backup/bootstrap.php`, folder `backup/`, dan permission folder backup.</p>
        <?php if ($backupDebug !== null): ?>
            <p class="text-muted">Detail: <?= htmlspecialchars($backupDebug, ENT_QUOTES, 'UTF-8'); ?></p>
        <?php endif; ?>
    </section>
<?php else: ?>
    <section class="card backup-panel">
        <h2>Bootstrap Version</h2>
        <p class="text-muted"><?= htmlspecialchars($backupBootstrapVersion, ENT_QUOTES, 'UTF-8'); ?></p>
    </section>

    <section class="backup-stats">
        <article class="card backup-stat"><span class="text-muted">Mode Backup</span><h2><?= htmlspecialchars($backupModeLabel, ENT_QUOTES, 'UTF-8'); ?></h2></article>
        <article class="card backup-stat"><span class="text-muted">Shell Access</span><h2><?= $config->shellAvailable() ? 'AVAILABLE' : 'BLOCKED'; ?></h2></article>
        <article class="card backup-stat"><span class="text-muted">Database Driver</span><h2><?= htmlspecialchars(strtoupper((string) ($config->databaseDriver ?? 'json')), ENT_QUOTES, 'UTF-8'); ?></h2></article>
        <article class="card backup-stat"><span class="text-muted">Compression</span><h2><?= htmlspecialchars(strtoupper((string) ($config->compressionFormat ?? 'zip')), ENT_QUOTES, 'UTF-8'); ?></h2></article>
    </section>

    <section class="backup-stats">
        <article class="card backup-stat"><span class="text-muted">Total Restore Point</span><h2><?= (int) $statistics['total']; ?></h2></article>
        <article class="card backup-stat"><span class="text-muted">Total Ukuran</span><h2><?= htmlspecialchars(backupFormatBytes((int) $statistics['bytes'])); ?></h2></article>
        <article class="card backup-stat"><span class="text-muted">Status Terakhir</span><h2><?= htmlspecialchars(strtoupper($lastStatus)); ?></h2></article>
        <article class="card backup-stat"><span class="text-muted">Waktu Terakhir</span><h2><?= htmlspecialchars($lastTime); ?></h2></article>
    </section>

    <section class="card backup-panel">
        <h2>Backup Manual</h2>
        <?php if (!$config->shellAvailable()): ?>
            <p class="text-muted">Hosting membatasi shell, jadi gunakan ZIP + JSON state agar backup tetap berjalan. Opsi tar.gz dan 7z disembunyikan dari mode web-friendly.</p>
        <?php endif; ?>
        <form class="form-row" action="backup-action.php" method="post">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken); ?>">
            <input type="hidden" name="action" value="create">
            <label class="field">Jenis<select name="type"><option value="full">Full</option><option value="incremental">Incremental</option></select></label>
            <label class="field">Kompresi<select name="compression"><option value="zip">ZIP</option><?php if ($config->shellAvailable()): ?><option value="tar.gz">TAR.GZ</option><option value="7z">7Z</option><?php endif; ?></select></label>
            <label class="field">Level<input name="compression_level" type="number" min="0" max="9" value="6"></label>
            <button class="button" type="submit">Buat Backup</button>
        </form>
    </section>

    <section class="card backup-panel">
        <div class="backup-actions">
            <div><h2>Restore Point</h2><p class="text-muted">Restore memverifikasi checksum dan membuat safety backup lebih dulu.</p></div>
            <form action="backup-action.php" method="post">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken); ?>">
                <input type="hidden" name="action" value="retention">
                <button class="button button--secondary" type="submit">Jalankan Retention</button>
            </form>
        </div>
        <table class="data-table">
            <thead><tr><th>Restore Point</th><th>Jenis</th><th>Tier</th><th>Ukuran</th><th>Storage</th><th>Aksi</th></tr></thead>
            <tbody>
            <?php foreach ($points as $point): ?>
                <tr>
                    <td><strong><?= htmlspecialchars((string) $point['snapshot_id']); ?></strong><br><span class="text-muted"><?= htmlspecialchars((string) $point['created_at']); ?></span></td>
                    <td><span class="backup-badge"><?= htmlspecialchars(strtoupper((string) $point['type'])); ?></span><br><span class="text-muted"><?= htmlspecialchars(strtoupper((string) $point['compression'])); ?><?= !empty($point['encrypted']) ? ' + AES-256' : ''; ?></span></td>
                    <td><?= htmlspecialchars((string) $point['retention_tier']); ?></td>
                    <td><?= htmlspecialchars(backupFormatBytes((int) $point['bytes'])); ?></td>
                    <td><?= backupSuccessfulOffsiteCount((array) ($point['offsite'] ?? [])); ?> lokasi offsite</td>
                    <td>
                        <form class="form-row form-row--compact" action="backup-action.php" method="post" onsubmit="return confirm('Pulihkan restore point ini? Safety backup akan dibuat lebih dulu.');">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken); ?>">
                            <input type="hidden" name="action" value="restore">
                            <input type="hidden" name="snapshot_id" value="<?= htmlspecialchars((string) $point['snapshot_id']); ?>">
                            <label><input type="checkbox" name="components[]" value="uploads" checked> Uploads</label>
                            <label><input type="checkbox" name="components[]" value="trash" checked> Trash</label>
                            <label><input type="checkbox" name="components[]" value="database" checked> Database</label>
                            <label><input type="checkbox" name="components[]" value="source"> Source</label>
                            <button class="button button--danger" type="submit">Restore</button>
                        </form>
                        <?php if (!empty($point['archive']) && substr(strtolower((string) $point['archive']), -4) === '.zip'): ?>
                            <?php backupRenderDownloadButton('Download ZIP', $backupDownloadAction, ['snapshot_id' => (string) $point['snapshot_id']]); ?>
                        <?php endif; ?>
                        <form action="backup-action.php" method="post">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken); ?>">
                            <input type="hidden" name="action" value="verify">
                            <input type="hidden" name="snapshot_id" value="<?= htmlspecialchars((string) $point['snapshot_id']); ?>">
                            <button class="button button--secondary" type="submit">Verify SHA-256</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if ($points === []): ?><tr><td colspan="6">Belum ada restore point.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </section>

    <section class="card backup-panel">
        <h2>Versi File</h2>
        <table class="data-table">
            <thead><tr><th>File</th><th>Versi</th><th>Waktu</th><th>Checksum</th><th>Aksi</th></tr></thead>
            <tbody>
            <?php foreach ($versions as $fileName => $entries): foreach (array_reverse($entries) as $entry): ?>
                <tr>
                    <td><?= htmlspecialchars((string) $fileName); ?></td>
                    <td><?= htmlspecialchars((string) $entry['version_id']); ?></td>
                    <td><?= htmlspecialchars((string) $entry['created_at']); ?></td>
                    <td><code><?= htmlspecialchars(substr((string) $entry['sha256'], 0, 16)); ?>...</code></td>
                    <td>
                        <div class="backup-inline-actions">
                            <form action="backup-action.php" method="post" onsubmit="return confirm('Pulihkan versi file ini?');" class="backup-inline-action">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken); ?>">
                            <input type="hidden" name="action" value="restore_version">
                            <input type="hidden" name="file_name" value="<?= htmlspecialchars((string) $fileName); ?>">
                            <input type="hidden" name="version_id" value="<?= htmlspecialchars((string) $entry['version_id']); ?>">
                            <button class="button button--secondary" type="submit">Restore Versi</button>
                            </form>
                            <?php if (!empty($entry['archive']) && substr(strtolower((string) $entry['archive']), -4) === '.zip'): ?>
                                <?php backupRenderDownloadButton('Download ZIP', $backupDownloadAction, ['archive' => (string) $entry['archive']]); ?>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
            <?php endforeach; endforeach; ?>
            <?php if ($versions === []): ?><tr><td colspan="5">Belum ada versi file.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </section>

    <section class="card backup-panel">
        <h2>Audit Log</h2>
        <table class="data-table">
            <thead><tr><th>Waktu</th><th>User</th><th>IP Address</th><th>Aktivitas</th><th>Status</th></tr></thead>
            <tbody>
            <?php foreach ($auditRows as $row): ?>
                <tr><td><?= htmlspecialchars((string) ($row['time'] ?? '')); ?></td><td><?= htmlspecialchars((string) ($row['user'] ?? '')); ?></td><td><?= htmlspecialchars((string) ($row['ip_address'] ?? '')); ?></td><td><?= htmlspecialchars((string) ($row['operation'] ?? '')); ?></td><td><?= htmlspecialchars((string) ($row['status'] ?? '')); ?></td></tr>
            <?php endforeach; ?>
            <?php if ($auditRows === []): ?><tr><td colspan="5">Belum ada audit log.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </section>
<?php endif; ?>

<?php dashboardRenderLayoutEnd(); ?>
