<?php

declare(strict_types=1);

require_once __DIR__ . DIRECTORY_SEPARATOR . 'auth.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'storage.php';

function dashboardBuildContext(): array
{
    $currentUser = authRequireLogin();
    if (!authCanUseDashboard($currentUser)) {
        header('Location: index.php?status=error_forbidden');
        exit;
    }

    if (!isset($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token']) || $_SESSION['csrf_token'] === '') {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    $uploadDir = storageUploadDir();
    $files = storageListFiles($uploadDir, $currentUser);
    $filesByOwner = [];
    foreach ($files as $file) {
        $owner = (string) ($file['owner'] ?? 'admin');
        $filesByOwner[$owner][] = $file;
    }

    $accounts = authAccounts();
    $storedAccounts = authStoredAccounts();
    foreach ($accounts as $username => $account) {
        if (in_array((string) ($account['role'] ?? ''), ['admin', 'user'], true)) {
            $filesByOwner[(string) $username] ??= [];
        }
    }

    $effectiveUploadLimitBytes = storageEffectiveUploadLimitBytes();
    $effectiveUploadLimitLabel = storageFormatFileSize($effectiveUploadLimitBytes);
    $allowedTypeLabel = storageAllowedTypeLabel();
    $allowedAcceptAttribute = storageAllowedAcceptAttribute();

    $userFiles = $currentUser['role'] === 'admin' ? $files : ($filesByOwner[$currentUser['username']] ?? []);
    $totalStorageBytes = array_sum(array_column($userFiles, 'size'));
    $totalStorageQuotaBytes = 1024 * 1024 * 1024;
    $storagePercentage = min(100, ($totalStorageBytes / $totalStorageQuotaBytes) * 100);
    $recentFiles = array_slice($userFiles, 0, 4);
    $imageCount = count(array_filter($userFiles, static fn(array $file): bool => (bool) $file['isImage']));
    $latestLabel = $userFiles !== [] ? storageFormatTimestamp((int) $userFiles[0]['modified']) : 'Belum ada berkas';

    $backupStatus = [];
    if ($currentUser['role'] === 'admin') {
        try {
            $backupBootstrap = __DIR__ . DIRECTORY_SEPARATOR . 'backup' . DIRECTORY_SEPARATOR . 'bootstrap.php';
            if (!is_file($backupBootstrap)) {
                throw new RuntimeException('Backup bootstrap tidak ditemukan.');
            }
            require_once $backupBootstrap;
            $backupStatus = (new BackupLogger(BackupConfig::fromEnvironment(__DIR__)))->readStatus();
        } catch (Throwable $exception) {
            error_log('hawpiwcloud dashboard backup status error: ' . $exception->getMessage());
            $backupStatus = [];
        }
    }
    $lastBackup = isset($backupStatus['last_backup']) && is_array($backupStatus['last_backup']) ? $backupStatus['last_backup'] : null;

    $status = $_GET['status'] ?? '';

    return [
        'currentUser' => $currentUser,
        'csrfToken' => $_SESSION['csrf_token'],
        'files' => $files,
        'filesByOwner' => $filesByOwner,
        'accounts' => $accounts,
        'storedAccounts' => $storedAccounts,
        'effectiveUploadLimitBytes' => $effectiveUploadLimitBytes,
        'effectiveUploadLimitLabel' => $effectiveUploadLimitLabel,
        'allowedTypeLabel' => $allowedTypeLabel,
        'allowedAcceptAttribute' => $allowedAcceptAttribute,
        'userFiles' => $userFiles,
        'totalStorageBytes' => $totalStorageBytes,
        'storagePercentage' => $storagePercentage,
        'recentFiles' => $recentFiles,
        'imageCount' => $imageCount,
        'latestLabel' => $latestLabel,
        'lastBackupStatus' => is_array($lastBackup) ? (string) ($lastBackup['status'] ?? 'unknown') : 'never_run',
        'lastBackupTime' => is_array($lastBackup) ? (string) ($lastBackup['time'] ?? '-') : 'Belum pernah dijalankan',
        'banner' => dashboardBuildBanner((string) $status, $effectiveUploadLimitLabel),
        'assetVersion' => (string) filemtime(__DIR__ . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'css' . DIRECTORY_SEPARATOR . 'dashboard.css'),
    ];
}

function dashboardBuildBanner(string $status, string $effectiveUploadLimitLabel): ?array
{
    return match ($status) {
        'upload_success' => ['type' => 'success', 'title' => 'Unggahan selesai', 'message' => 'Berkas Anda berhasil ditambahkan ke penyimpanan awan.'],
        'delete_success' => ['type' => 'success', 'title' => 'Berkas dipindahkan', 'message' => 'Berkas yang dipilih dipindahkan ke Trash dan masih dapat dipulihkan selama masa retensi.'],
        'restore_success' => ['type' => 'success', 'title' => 'File dipulihkan', 'message' => 'File berhasil dikembalikan dari Trash ke daftar upload.'],
        'access_success' => ['type' => 'success', 'title' => 'Akses diperbarui', 'message' => 'Pengaturan tabel Viewer berhasil disimpan.'],
        'user_create_success' => ['type' => 'success', 'title' => 'User dibuat', 'message' => 'Akun baru berhasil ditambahkan dan sudah bisa digunakan untuk login.'],
        'user_delete_success' => ['type' => 'success', 'title' => 'User dihapus', 'message' => 'Akun pengguna berhasil dihapus dari daftar login.'],
        'error_permissions' => ['type' => 'error', 'title' => 'Penyimpanan tidak tersedia', 'message' => 'Layanan penyimpanan sedang tidak tersedia sementara. Silakan coba lagi beberapa saat lagi.'],
        'error_size' => ['type' => 'error', 'title' => 'Berkas terlalu besar', 'message' => 'Ukuran berkas melebihi batas unggahan saat ini (' . $effectiveUploadLimitLabel . '). Silakan pilih berkas yang lebih kecil lalu coba lagi.'],
        'error_server_limit' => ['type' => 'error', 'title' => 'Berkas terlalu besar', 'message' => 'Ukuran berkas melampaui batas unggahan saat ini (' . $effectiveUploadLimitLabel . '). Silakan kompres berkas atau pilih file lain yang lebih kecil.'],
        'error_partial' => ['type' => 'error', 'title' => 'Unggahan terputus', 'message' => 'Proses unggahan berkas belum selesai. Silakan coba lagi.'],
        'error_nofile' => ['type' => 'error', 'title' => 'Tidak ada berkas dipilih', 'message' => 'Pilih berkas terlebih dahulu sebelum mengirim formulir unggahan.'],
        'error_type' => ['type' => 'error', 'title' => 'Jenis berkas tidak didukung', 'message' => 'File yang dipilih tidak sesuai dengan jenis yang diizinkan untuk diunggah.'],
        'error_security' => ['type' => 'error', 'title' => 'Permintaan tidak valid', 'message' => 'Sesi atau token keamanan tidak cocok. Silakan muat ulang halaman dan coba lagi.'],
        'error_forbidden' => ['type' => 'error', 'title' => 'Akses ditolak', 'message' => 'Role akun Anda tidak memiliki kewenangan untuk melakukan aksi tersebut.'],
        'user_create_duplicate' => ['type' => 'error', 'title' => 'Username sudah ada', 'message' => 'Gunakan username lain karena username tersebut sudah terdaftar.'],
        'user_create_invalid_username' => ['type' => 'error', 'title' => 'Username tidak valid', 'message' => 'Username harus 3-32 karakter dan hanya boleh memakai huruf, angka, garis bawah, atau strip.'],
        'user_create_invalid_password' => ['type' => 'error', 'title' => 'Password terlalu pendek', 'message' => 'Password user baru minimal 6 karakter.'],
        'user_create_invalid_role' => ['type' => 'error', 'title' => 'Role tidak valid', 'message' => 'Pilih role admin, user, atau viewer.'],
        'user_delete_protected', 'user_delete_self' => ['type' => 'error', 'title' => 'User tidak bisa dihapus', 'message' => 'Akun admin utama atau akun yang sedang aktif tidak boleh dihapus.'],
        'user_delete_missing' => ['type' => 'error', 'title' => 'User tidak ditemukan', 'message' => 'Akun yang ingin dihapus tidak tersedia pada data user.'],
        'error' => ['type' => 'error', 'title' => 'Terjadi kesalahan', 'message' => 'Silakan coba lagi dan pastikan berkas yang dipilih sudah benar.'],
        default => null,
    };
}
