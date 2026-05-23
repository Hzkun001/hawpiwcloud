<?php

declare(strict_types=1);

require_once __DIR__ . DIRECTORY_SEPARATOR . 'auth.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'storage.php';

$currentUser = authRequireLogin();
if (!authCanUseDashboard($currentUser)) {
    header('Location: index.php?status=error_forbidden');
    exit;
}

if (!isset($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token']) || $_SESSION['csrf_token'] === '') {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$csrfToken = $_SESSION['csrf_token'];

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
$allowedTypeLabel = storageAllowedTypeLabel();
$allowedAcceptAttribute = storageAllowedAcceptAttribute();

$userFiles = $currentUser['role'] === 'admin' ? $files : ($filesByOwner[$currentUser['username']] ?? []);
$totalStorageBytes = array_sum(array_column($userFiles, 'size'));
$totalStorageQuotaBytes = 1 * 1024 * 1024 * 1024; // 1 GB dummy quota
$storagePercentage = min(100, ($totalStorageBytes / $totalStorageQuotaBytes) * 100);
$recentFiles = array_slice($userFiles, 0, 4);

$imageCount = count(array_filter($userFiles, static fn(array $file): bool => (bool) $file['isImage']));
$latestLabel = $userFiles !== [] ? formatTimestamp((int) $userFiles[0]['modified']) : 'Belum ada berkas';


$status = $_GET['status'] ?? '';
$banner = null;
$assetVersion = (string) filemtime(__DIR__ . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'styles.css');

if ($status === 'upload_success') {
    $banner = ['type' => 'success', 'title' => 'Unggahan selesai', 'message' => 'Berkas Anda berhasil ditambahkan ke penyimpanan awan.'];
} elseif ($status === 'delete_success') {
    $banner = ['type' => 'success', 'title' => 'Berkas dihapus', 'message' => 'Berkas yang dipilih berhasil dihapus.'];
} elseif ($status === 'access_success') {
    $banner = ['type' => 'success', 'title' => 'Akses diperbarui', 'message' => 'Pengaturan tabel Viewer dan Guest berhasil disimpan.'];
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
    $banner = ['type' => 'error', 'title' => 'Akses ditolak', 'message' => 'Role akun Anda tidak memiliki kewenangan untuk melakukan aksi tersebut.'];
} elseif ($status === 'user_create_success') {
    $banner = ['type' => 'success', 'title' => 'User dibuat', 'message' => 'Akun baru berhasil ditambahkan dan sudah bisa digunakan untuk login.'];
} elseif ($status === 'user_delete_success') {
    $banner = ['type' => 'success', 'title' => 'User dihapus', 'message' => 'Akun pengguna berhasil dihapus dari daftar login.'];
} elseif ($status === 'user_create_duplicate') {
    $banner = ['type' => 'error', 'title' => 'Username sudah ada', 'message' => 'Gunakan username lain karena username tersebut sudah terdaftar.'];
} elseif ($status === 'user_create_invalid_username') {
    $banner = ['type' => 'error', 'title' => 'Username tidak valid', 'message' => 'Username harus 3-32 karakter dan hanya boleh memakai huruf, angka, garis bawah, atau strip.'];
} elseif ($status === 'user_create_invalid_password') {
    $banner = ['type' => 'error', 'title' => 'Password terlalu pendek', 'message' => 'Password user baru minimal 6 karakter.'];
} elseif ($status === 'user_create_invalid_role') {
    $banner = ['type' => 'error', 'title' => 'Role tidak valid', 'message' => 'Pilih role admin, user, atau viewer.'];
} elseif ($status === 'user_delete_protected' || $status === 'user_delete_self') {
    $banner = ['type' => 'error', 'title' => 'User tidak bisa dihapus', 'message' => 'Akun admin utama atau akun yang sedang aktif tidak boleh dihapus.'];
} elseif ($status === 'user_delete_missing') {
    $banner = ['type' => 'error', 'title' => 'User tidak ditemukan', 'message' => 'Akun yang ingin dihapus tidak tersedia pada data user.'];
} elseif ($status === 'error') {
    $banner = ['type' => 'error', 'title' => 'Terjadi kesalahan', 'message' => 'Silakan coba lagi dan pastikan berkas yang dipilih sudah benar.'];
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>hawpiwcloud Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    <link rel="stylesheet" href="assets/styles.css?v=<?= htmlspecialchars($assetVersion, ENT_QUOTES, 'UTF-8'); ?>">
    <style>
        /* Premium Dashboard Styles */
        :root {
            --dash-sidebar-bg: #0f172a;
            --dash-sidebar-border: rgba(255, 255, 255, 0.05);
            --dash-sidebar-text: #94a3b8;
            --dash-sidebar-hover: #ffffff;
            --dash-sidebar-active-bg: rgba(255, 255, 255, 0.1);
            --dash-sidebar-active-border: #6366f1;

            --dash-main-bg: #f4f7fc;
            --dash-header-bg: rgba(255, 255, 255, 0.85);
            --dash-header-border: rgba(0, 0, 0, 0.05);
            
            --dash-accent: #6366f1;
            --dash-accent-hover: #4f46e5;
        }

        body.dashboard-body {
            background: var(--dash-main-bg) !important;
            margin: 0;
            padding: 0;
            height: 100vh;
            overflow: hidden;
            font-family: 'Inter', sans-serif;
        }

        .dashboard-layout {
            display: flex;
            height: 100vh;
            width: 100vw;
            overflow: hidden;
        }

        /* Sidebar Styling */
        .dashboard-sidebar {
            width: 280px;
            background: var(--dash-sidebar-bg);
            border-right: 1px solid var(--dash-sidebar-border);
            display: flex;
            flex-direction: column;
            z-index: 100;
            transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 4px 0 24px rgba(0, 0, 0, 0.1);
            color: #fff;
        }

        .dashboard-sidebar-header {
            height: 80px;
            padding: 0 28px;
            display: flex;
            align-items: center;
            border-bottom: 1px solid var(--dash-sidebar-border);
        }

        .dashboard-sidebar-header .brand {
            display: flex;
            align-items: center;
            gap: 12px;
            text-decoration: none;
            color: #fff;
        }

        .dashboard-sidebar-header .brand-mark {
            display: grid;
            place-items: center;
            width: 40px;
            height: 40px;
            border-radius: 12px;
            background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
            color: #fff;
            font-size: 1.2rem;
            box-shadow: 0 4px 12px rgba(99, 102, 241, 0.3);
        }

        .dashboard-sidebar-header .brand-name {
            font-family: 'Manrope', sans-serif;
            font-size: 1.3rem;
            font-weight: 800;
            letter-spacing: -0.02em;
        }

        .dashboard-nav {
            flex: 1;
            padding: 24px 16px;
            display: flex;
            flex-direction: column;
            gap: 6px;
            overflow-y: auto;
        }

        .dashboard-nav-link {
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 14px 18px;
            border-radius: 14px;
            color: var(--dash-sidebar-text);
            font-weight: 600;
            font-size: 0.95rem;
            text-decoration: none;
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
        }

        .dashboard-nav-link i {
            font-size: 1.2rem;
            width: 24px;
            text-align: center;
            transition: color 0.2s;
        }

        .dashboard-nav-link:hover {
            background: var(--dash-sidebar-active-bg);
            color: var(--dash-sidebar-hover);
        }

        .dashboard-nav-link:hover i {
            color: var(--dash-accent);
        }

        .dashboard-sidebar-footer {
            padding: 20px 16px;
            border-top: 1px solid var(--dash-sidebar-border);
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .dashboard-nav-logout {
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 14px 18px;
            border-radius: 14px;
            color: #f87171;
            font-weight: 600;
            font-size: 0.95rem;
            text-decoration: none;
            transition: all 0.2s;
        }

        .dashboard-nav-logout:hover {
            background: rgba(248, 113, 113, 0.1);
            color: #fca5a5;
        }

        /* Main Area */
        .dashboard-main {
            flex: 1;
            display: flex;
            flex-direction: column;
            min-width: 0;
            overflow-y: auto;
            background: var(--dash-main-bg);
        }

        .dashboard-header {
            height: 80px;
            padding: 0 40px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: var(--dash-header-bg);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border-bottom: 1px solid var(--dash-header-border);
            position: sticky;
            top: 0;
            z-index: 50;
        }

        .dashboard-header-left {
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .dashboard-mobile-toggle {
            display: none;
            background: none;
            border: none;
            font-size: 1.5rem;
            color: #334155;
            cursor: pointer;
            padding: 8px;
            border-radius: 8px;
        }

        .dashboard-mobile-toggle:hover {
            background: rgba(0,0,0,0.05);
        }

        .dashboard-header-title {
            font-family: 'Manrope', sans-serif;
            font-size: 1.4rem;
            font-weight: 800;
            color: #0f172a;
            margin: 0 0 2px 0;
            letter-spacing: -0.02em;
        }

        .dashboard-header-subtitle {
            font-size: 0.85rem;
            color: #64748b;
            display: block;
        }

        .dashboard-header-user {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 6px 20px 6px 6px;
            background: #ffffff;
            border: 1px solid rgba(0,0,0,0.06);
            border-radius: 999px;
            box-shadow: 0 4px 14px rgba(0, 0, 0, 0.03);
            cursor: pointer;
            transition: box-shadow 0.2s, transform 0.2s;
        }

        .dashboard-header-user:hover {
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.06);
            transform: translateY(-1px);
        }

        .dashboard-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, #e0e7ff 0%, #c7d2fe 100%);
            color: #4338ca;
            display: grid;
            place-items: center;
            font-weight: 800;
            font-size: 1.1rem;
            font-family: 'Manrope', sans-serif;
        }

        .dashboard-user-info {
            display: flex;
            flex-direction: column;
            line-height: 1.2;
        }

        .dashboard-user-name {
            font-weight: 700;
            font-size: 0.95rem;
            color: #1e293b;
        }

        .dashboard-user-role {
            font-size: 0.75rem;
            color: #8b5cf6;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            font-weight: 800;
            margin-top: 2px;
        }

        .dashboard-content {
            padding: 36px 40px;
            max-width: 1600px;
            margin: 0 auto;
            width: 100%;
        }

        /* Adjustments for the inner cards inside the dashboard */
        .dashboard-main .panel {
            border-radius: 24px;
            border: 1px solid rgba(0,0,0,0.04);
            box-shadow: 0 10px 30px rgba(15, 23, 42, 0.04);
            background: #ffffff;
        }

        .dashboard-main .panel-head {
            padding: 24px 28px 20px;
            border-bottom: 1px solid rgba(0,0,0,0.04);
            background: transparent;
        }

        .dashboard-main .panel-head h2 {
            font-size: 1.2rem;
            color: #0f172a;
        }

        /* Mobile responsive */
        @media (max-width: 1024px) {
            .dashboard-sidebar {
                position: fixed;
                left: 0;
                top: 0;
                bottom: 0;
                transform: translateX(-100%);
            }

            .dashboard-sidebar.is-open {
                transform: translateX(0);
                box-shadow: 20px 0 40px rgba(0,0,0,0.2);
            }

            .dashboard-mobile-toggle {
                display: block;
            }

            .dashboard-header {
                padding: 0 24px;
            }

            .dashboard-content {
                padding: 24px;
            }
        }

        /* Dashboard Widgets */
        .dashboard-widgets {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 24px;
        }

        .widget-card {
            background: #ffffff;
            border-radius: 20px;
            padding: 24px;
            display: flex;
            align-items: flex-start;
            gap: 16px;
            border: 1px solid rgba(0,0,0,0.04);
            box-shadow: 0 4px 20px rgba(15, 23, 42, 0.03);
            transition: transform 0.2s, box-shadow 0.2s;
        }

        .widget-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 30px rgba(15, 23, 42, 0.06);
        }

        .widget-icon {
            width: 48px;
            height: 48px;
            border-radius: 14px;
            display: grid;
            place-items: center;
            font-size: 1.4rem;
            flex-shrink: 0;
        }

        .widget-info {
            flex: 1;
            min-width: 0;
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .widget-label {
            font-size: 0.85rem;
            color: #64748b;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .widget-value {
            font-family: 'Manrope', sans-serif;
            font-size: 1.4rem;
            color: #0f172a;
            font-weight: 800;
            letter-spacing: -0.02em;
            line-height: 1.2;
        }

        .widget-subtext {
            font-size: 0.85rem;
            color: #94a3b8;
        }

        /* Progress Bar */
        .progress-bar-bg {
            width: 100%;
            height: 6px;
            background: #e2e8f0;
            border-radius: 999px;
            margin-top: 8px;
            overflow: hidden;
        }

        .progress-bar-fill {
            height: 100%;
            background: linear-gradient(90deg, #6366f1 0%, #8b5cf6 100%);
            border-radius: 999px;
            transition: width 1s cubic-bezier(0.4, 0, 0.2, 1);
        }

        /* Recent Activity Mini */
        .recent-activity-widget {
            flex-direction: row;
        }

        .recent-files-mini {
            display: flex;
            gap: 8px;
            margin-top: 8px;
        }

        .recent-file-item {
            width: 36px;
            height: 36px;
            border-radius: 10px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            display: grid;
            place-items: center;
            font-size: 1rem;
            cursor: pointer;
            transition: all 0.2s;
        }

        .recent-file-item:hover {
            transform: translateY(-2px) scale(1.05);
            border-color: #cbd5e1;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
        }

        /* Table Hover Actions & File Icons */
        .file-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
        }

        .file-name {
            display: flex;
            align-items: center;
            gap: 12px;
            min-width: 0;
        }

        .file-name span {
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            font-weight: 500;
            color: #1e293b;
        }

        .file-actions {
            opacity: 0;
            transform: translateX(10px);
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
            background: rgba(255,255,255,0.9);
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
            padding: 4px;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            border: 1px solid rgba(0,0,0,0.05);
        }

        tr:hover .file-actions {
            opacity: 1;
            transform: translateX(0);
        }

        /* Empty State SVG */
        .empty-svg {
            margin-bottom: 20px;
            animation: float 6s ease-in-out infinite;
        }

        @keyframes float {
            0% { transform: translateY(0px); }
            50% { transform: translateY(-10px); }
            100% { transform: translateY(0px); }
        }

        /* Premium Tables */
        .table-wrap {
            width: 100%;
            overflow-x: auto;
            border-radius: 16px;
            border: 1px solid rgba(0,0,0,0.05);
            background: #ffffff;
            margin-top: 16px;
        }

        .dashboard-main table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            text-align: left;
            min-width: 600px;
        }

        .dashboard-main th {
            background: #f8fafc;
            color: #64748b;
            font-weight: 600;
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            padding: 16px 24px;
            border-bottom: 1px solid rgba(0,0,0,0.05);
            white-space: nowrap;
        }

        .dashboard-main td {
            padding: 16px 24px;
            vertical-align: middle;
            border-bottom: 1px solid rgba(0,0,0,0.03);
            color: #334155;
            font-size: 0.95rem;
        }

        .dashboard-main tbody tr {
            transition: background 0.2s;
        }

        .dashboard-main tbody tr:hover {
            background: #f8fafc;
        }

        .dashboard-main tbody tr:last-child td {
            border-bottom: none;
        }

        .dashboard-main td.meta {
            color: #64748b;
        }

        /* User Create Card (Manajemen User Form) */
        .user-create-card {
            background: #f8fafc;
            border-radius: 16px;
            padding: 24px;
            border: 1px solid rgba(0,0,0,0.05);
            margin-bottom: 24px;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            align-items: end;
        }

        .user-create-head {
            grid-column: 1 / -1;
            margin-bottom: 8px;
        }

        .user-create-head h3 {
            margin: 0 0 4px 0;
            font-size: 1.1rem;
            color: #0f172a;
        }

        .user-create-head p {
            margin: 0;
            font-size: 0.85rem;
            color: #64748b;
        }

        .user-create-card label {
            display: flex;
            flex-direction: column;
            gap: 8px;
            font-size: 0.85rem;
            font-weight: 600;
            color: #334155;
        }

        .user-create-card input,
        .user-create-card select {
            padding: 12px 16px;
            border: 1px solid #cbd5e1;
            border-radius: 12px;
            font-family: inherit;
            font-size: 0.95rem;
            outline: none;
            transition: border-color 0.2s, box-shadow 0.2s;
            background: #ffffff;
        }

        .user-create-card input:focus,
        .user-create-card select:focus {
            border-color: #6366f1;
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
        }

        .user-create-card button {
            height: 46px; /* match input height */
            padding: 0 24px;
            background: #0f172a;
            color: #ffffff;
            border: none;
            border-radius: 12px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.2s, transform 0.2s;
        }

        .user-create-card button:hover {
            background: #1e293b;
            transform: translateY(-1px);
        }

        /* Pill Badges */
        .status-pill {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 999px;
            background: rgba(99, 102, 241, 0.1);
            color: #4f46e5;
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        @media (max-width: 768px) {
            .dashboard-widgets {
                grid-template-columns: 1fr;
            }
            .file-actions {
                opacity: 1;
                transform: translateX(0);
                background: transparent;
                border: none;
                box-shadow: none;
                padding: 0;
            }
        }
    </style>
</head>

<body class="dashboard-body">
    <div class="dashboard-layout">
        <aside class="dashboard-sidebar" id="sidebar">
            <div class="dashboard-sidebar-header">
                <a class="brand" href="dashboard.php" aria-label="Dashboard hawpiwcloud">
                    <span class="brand-mark" aria-hidden="true">
                        <i class="fa-solid fa-cloud-arrow-up"></i>
                    </span>
                    <span class="brand-name" style="font-size: 1.4rem;">hawpiwcloud</span>
                </a>
            </div>
            
            <nav class="dashboard-nav" aria-label="Primary">
                <a class="dashboard-nav-link" href="#upload-panel">
                    <i class="fa-solid fa-cloud-arrow-up"></i> Unggah Berkas
                </a>
                <a class="dashboard-nav-link" href="#files">
                    <i class="fa-solid fa-file-lines"></i> Kelola Berkas
                </a>
                <?php if ($currentUser['role'] === 'admin'): ?>
                    <a class="dashboard-nav-link" href="#users">
                        <i class="fa-solid fa-users"></i> Manajemen User
                    </a>
                <?php endif; ?>
            </nav>

            <div class="dashboard-sidebar-footer">
                <a class="dashboard-nav-link" href="index.php">
                    <i class="fa-solid fa-house"></i> Halaman Depan
                </a>
                <a class="dashboard-nav-logout" href="logout.php">
                    <i class="fa-solid fa-right-from-bracket"></i> Logout
                </a>
            </div>
        </aside>

        <main class="dashboard-main">
            <header class="dashboard-header">
                <div class="dashboard-header-left">
                    <button class="dashboard-mobile-toggle" id="mobile-toggle" aria-label="Toggle menu">
                        <i class="fa-solid fa-bars"></i>
                    </button>
                    <div>
                        <h1 class="dashboard-header-title">Dashboard</h1>
                        <span class="dashboard-header-subtitle">
                            <?= $currentUser['role'] === 'admin' ? 'Kelola seluruh pengguna dan tabel file' : 'Kelola tabel penyimpanan pribadi Anda'; ?>
                        </span>
                    </div>
                </div>
                
                <div class="dashboard-header-user">
                    <div class="dashboard-avatar">
                        <?= htmlspecialchars(strtoupper(substr($currentUser['name'], 0, 1)), ENT_QUOTES, 'UTF-8'); ?>
                    </div>
                    <div class="dashboard-user-info">
                        <span class="dashboard-user-name"><?= htmlspecialchars($currentUser['name'], ENT_QUOTES, 'UTF-8'); ?></span>
                        <span class="dashboard-user-role"><?= htmlspecialchars(authRoleLabel($currentUser), ENT_QUOTES, 'UTF-8'); ?></span>
                    </div>
                </div>
            </header>

            <div class="dashboard-content stack">

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

            <div class="dashboard-widgets" id="overview">
                <!-- Widget 1: Storage Quota -->
                <div class="widget-card">
                    <div class="widget-icon" style="background: rgba(99, 102, 241, 0.1); color: #6366f1;">
                        <i class="fa-solid fa-hard-drive"></i>
                    </div>
                    <div class="widget-info">
                        <span class="widget-label">Kapasitas Terpakai</span>
                        <strong class="widget-value"><?= formatFileSize($totalStorageBytes) ?> / 1 GB</strong>
                        <div class="progress-bar-bg">
                            <div class="progress-bar-fill" style="width: <?= htmlspecialchars((string)$storagePercentage, ENT_QUOTES, 'UTF-8') ?>%;"></div>
                        </div>
                    </div>
                </div>

                <!-- Widget 2: Total Files -->
                <div class="widget-card">
                    <div class="widget-icon" style="background: rgba(16, 185, 129, 0.1); color: #10b981;">
                        <i class="fa-solid fa-file-lines"></i>
                    </div>
                    <div class="widget-info">
                        <span class="widget-label">Total Berkas</span>
                        <strong class="widget-value"><?= count($userFiles) ?> Berkas</strong>
                        <span class="widget-subtext">Termasuk <?= $imageCount ?> gambar. Terbaru: <?= htmlspecialchars($latestLabel, ENT_QUOTES, 'UTF-8') ?></span>
                    </div>
                </div>

                <!-- Widget 3: Recent Activity -->
                <div class="widget-card recent-activity-widget">
                    <div class="widget-icon" style="background: rgba(245, 158, 11, 0.1); color: #f59e0b;">
                        <i class="fa-solid fa-clock-rotate-left"></i>
                    </div>
                    <div class="widget-info">
                        <span class="widget-label">Aktivitas Terbaru</span>
                        <?php if (!empty($recentFiles)): ?>
                            <div class="recent-files-mini">
                                <?php foreach ($recentFiles as $rFile): 
                                    $ext = strtolower(pathinfo($rFile['name'], PATHINFO_EXTENSION));
                                    $iconClass = 'fa-file';
                                    $iconColor = '#94a3b8';
                                    
                                    if ($rFile['isImage']) { $iconClass = 'fa-image'; $iconColor = '#3b82f6'; }
                                    elseif (in_array($ext, ['pdf'])) { $iconClass = 'fa-file-pdf'; $iconColor = '#ef4444'; }
                                    elseif (in_array($ext, ['zip', 'rar', 'tar', 'gz'])) { $iconClass = 'fa-file-zipper'; $iconColor = '#eab308'; }
                                    elseif (in_array($ext, ['doc', 'docx', 'txt', 'rtf'])) { $iconClass = 'fa-file-word'; $iconColor = '#10b981'; }
                                ?>
                                    <div class="recent-file-item" title="<?= htmlspecialchars($rFile['name'], ENT_QUOTES, 'UTF-8') ?>">
                                        <i class="fa-solid <?= $iconClass ?>" style="color: <?= $iconColor ?>;"></i>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <span class="widget-subtext">Belum ada aktivitas</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <section class="panel" id="upload-panel" aria-labelledby="upload-title">
                <div class="panel-head">
                    <div>
                        <h2 id="upload-title">Unggah Berkas</h2>
                        <span>Gunakan panel ini untuk menambah file baru. Anda dapat mengatur akses agar file ini dapat dilihat oleh Viewer atau Guest. File Anda tersimpan secara aman.</span>
                    </div>
                </div>

                <div class="upload-card">
                    <form action="upload.php" method="post" enctype="multipart/form-data" id="upload-form">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                        <div class="upload-grid">
                            <label class="dropzone" id="dropzone" for="file-input">
                                <input class="dropzone-input" id="file-input" type="file" name="fileToUpload" accept="<?= htmlspecialchars($allowedAcceptAttribute, ENT_QUOTES, 'UTF-8'); ?>" data-max-file-bytes="<?= htmlspecialchars((string) $effectiveUploadLimitBytes, ENT_QUOTES, 'UTF-8'); ?>" data-max-file-label="<?= htmlspecialchars($effectiveUploadLimitLabel, ENT_QUOTES, 'UTF-8'); ?>" data-allowed-file-types="<?= htmlspecialchars($allowedTypeLabel, ENT_QUOTES, 'UTF-8'); ?>" required>
                                <div class="dropzone-content" id="dropzone-content">
                                    <div class="dropzone-icon" aria-hidden="true">
                                        <i class="fa-solid fa-cloud-arrow-up"></i>
                                    </div>
                                    <div class="dropzone-title">Klik untuk mengunggah atau seret dan lepaskan</div>
                                    <p class="dropzone-copy">Format yang diizinkan: <?= htmlspecialchars($allowedTypeLabel, ENT_QUOTES, 'UTF-8'); ?>. Pratinjau akan tampil otomatis untuk gambar.</p>
                                    <span class="file-chip" id="file-chip">Belum ada berkas yang dipilih</span>
                                </div>
                            </label>

                            <aside class="preview-panel" aria-live="polite">
                                <div class="preview-titlebar">
                                    <div class="window-dots" aria-hidden="true">
                                        <span></span>
                                        <span></span>
                                        <span></span>
                                    </div>
                                    <div class="preview-title">Pratinjau hawpiwcloud</div>
                                </div>
                                <div class="preview-shell">
                                    <div class="preview-empty" id="preview-empty">
                                        <i class="fa-solid fa-file-lines" aria-hidden="true"></i>
                                        <div>Pratinjau unggahan akan muncul di sini</div>
                                        <span>Pilih gambar atau dokumen untuk memastikan berkas sebelum dikirim.</span>
                                    </div>
                                    <img class="preview-image" id="preview-image" alt="Pratinjau berkas">
                                    <div class="preview-icon" id="preview-icon" aria-hidden="true">
                                        <i class="fa-solid fa-file-lines"></i>
                                    </div>
                                </div>

                                <div class="preview-meta">
                                    <strong id="preview-name">Belum ada berkas yang dipilih</strong>
                                    <span id="preview-details">Ukuran dan jenis berkas akan tampil di sini.</span>
                                    <?php if (in_array($currentUser['role'], ['admin', 'user'], true)): ?>
                                        <div class="access-options" aria-label="Akses khusus">
                                            <label>
                                                <input type="checkbox" name="viewer_access" checked>
                                                <span>Masukkan ke tabel Viewer</span>
                                            </label>
                                            <label>
                                                <input type="checkbox" name="guest_access">
                                                <span>Masukkan ke tabel Guest</span>
                                            </label>
                                        </div>
                                    <?php endif; ?>
                                    <div class="helper-note">
                                        <span><strong>Tips:</strong> Gambar akan menampilkan thumbnail secara otomatis.</span>
                                        <span>Batas: <?= htmlspecialchars($effectiveUploadLimitLabel); ?>. Format: <?= htmlspecialchars($allowedTypeLabel, ENT_QUOTES, 'UTF-8'); ?>.</span>
                                    </div>
                                    <p class="upload-feedback" id="upload-feedback" role="alert" aria-live="assertive" hidden></p>
                                </div>

                                <div class="upload-actions">
                                    <button class="secondary-button" type="button" id="clear-file">Atur Ulang</button>
                                    <button class="primary-button" type="submit">
                                        <i class="button-icon fa-solid fa-cloud-arrow-up" aria-hidden="true"></i>
                                        Unggah Berkas
                                    </button>
                                </div>
                            </aside>
                        </div>
                    </form>
                </div>
            </section>

            <?php if ($currentUser['role'] === 'admin'): ?>
                <section class="panel files-panel" aria-labelledby="users-title" id="users">
                    <div class="panel-head">
                        <div>
                            <h2 id="users-title">Manajemen User</h2>
                            <span><?= count($accounts); ?> akun terdaftar</span>
                        </div>
                    </div>

                    <div class="user-management">
                        <form class="user-create-card" action="users.php" method="post">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                            <input type="hidden" name="action" value="create">

                            <div class="user-create-head">
                                <h3>Buat User Baru</h3>
                                <p>Admin dapat menambahkan banyak akun untuk kebutuhan tabel cloud masing-masing pengguna.</p>
                            </div>

                            <label>
                                <span>Nama Lengkap</span>
                                <input type="text" name="name" placeholder="Contoh: Hafidz" required>
                            </label>

                            <label>
                                <span>Username</span>
                                <input type="text" name="username" placeholder="huruf_angka" pattern="[A-Za-z0-9_-]{3,32}" required>
                            </label>

                            <label>
                                <span>Password</span>
                                <input type="password" name="password" minlength="6" placeholder="Minimal 6 karakter" required>
                            </label>

                            <label>
                                <span>Level</span>
                                <select name="role" required>
                                    <option value="user">User</option>
                                    <option value="viewer">Viewer</option>
                                    <option value="admin">Admin</option>
                                </select>
                            </label>

                            <button class="primary-button" type="submit">Tambah User</button>
                        </form>
                    </div>

                    <div class="table-wrap">
                        <table>
                            <thead>
                                <tr>
                                    <th>Username</th>
                                    <th>Nama</th>
                                    <th>Level</th>
                                    <th>File</th>
                                    <th>Kewenangan Utama</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($accounts as $username => $account): ?>
                                    <tr>
                                        <td data-label="Username"><?= htmlspecialchars((string) $username, ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td class="meta" data-label="Nama"><?= htmlspecialchars((string) ($account['name'] ?? $username), ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td class="meta" data-label="Level"><?= htmlspecialchars(HAWPIWCLOUD_ROLE_LABELS[$account['role']] ?? $account['role'], ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td class="meta" data-label="File"><?= count($filesByOwner[(string) $username] ?? []); ?> file</td>
                                        <td class="meta" data-label="Kewenangan Utama"><?= htmlspecialchars(authRoleDescription((string) $account['role']), ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td class="meta" data-label="Aksi">
                                            <?php if (isset($storedAccounts[$username]) && $username !== $currentUser['username']): ?>
                                                <form class="action-form" action="users.php" method="post" onsubmit="return confirm('Hapus user ini? File miliknya tetap tersimpan dan tetap bisa dipantau admin.');">
                                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                                                    <input type="hidden" name="action" value="delete">
                                                    <input type="hidden" name="username" value="<?= htmlspecialchars((string) $username, ENT_QUOTES, 'UTF-8'); ?>">
                                                    <button class="action-button delete" type="submit">Hapus</button>
                                                </form>
                                            <?php elseif (isset($storedAccounts[$username])): ?>
                                                <span class="status-pill">Sedang aktif</span>
                                            <?php else: ?>
                                                <span class="status-pill">Akun demo</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                <tr>
                                    <td data-label="Username">guest</td>
                                    <td class="meta" data-label="Nama">Guest</td>
                                    <td class="meta" data-label="Level">Guest</td>
                                    <td class="meta" data-label="File">Tabel khusus</td>
                                    <td class="meta" data-label="Kewenangan Utama"><?= htmlspecialchars(authRoleDescription('guest'), ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td class="meta" data-label="Aksi"><span class="status-pill">Tanpa akun</span></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>
            <?php endif; ?>

            <?php if ($currentUser['role'] === 'admin'): ?>
                <section class="panel files-panel" aria-labelledby="owner-files-title" id="owner-files">
                    <div class="panel-head">
                        <div>
                            <h2 id="owner-files-title">Tabel Penyimpanan Per User</h2>
                            <span>Admin melihat setiap tabel penyimpanan berdasarkan pemilik file</span>
                        </div>
                    </div>

                    <div class="owner-table-stack">
                        <?php if ($filesByOwner !== []): ?>
                            <?php foreach ($filesByOwner as $owner => $ownerFiles): ?>
                                <section class="owner-table" aria-labelledby="owner-title-<?= htmlspecialchars($owner, ENT_QUOTES, 'UTF-8'); ?>">
                                    <div class="owner-table-head">
                                        <h3 id="owner-title-<?= htmlspecialchars($owner, ENT_QUOTES, 'UTF-8'); ?>">
                                            <?= htmlspecialchars($owner, ENT_QUOTES, 'UTF-8'); ?>
                                            <?= !isset($accounts[$owner]) ? ' <span class="status-pill" style="font-size: 0.7em; margin-left: 8px; vertical-align: middle;">(Dihapus)</span>' : ''; ?>
                                        </h3>
                                        <span><?= count($ownerFiles); ?> file</span>
                                    </div>
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
                                                <?php foreach ($ownerFiles as $file): ?>
                                                    <tr>
                                                        <td data-label="Nama Berkas"><?= htmlspecialchars($file['name'], ENT_QUOTES, 'UTF-8'); ?></td>
                                                        <td class="meta" data-label="Ukuran"><?= formatFileSize((int) $file['size']); ?></td>
                                                        <td class="meta" data-label="Terakhir Diubah"><?= formatTimestamp((int) $file['modified']); ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </section>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="empty-state">
                                <div class="empty-card">
                                    <h3>Belum ada tabel user</h3>
                                    <p>File yang diunggah admin atau user akan dikelompokkan otomatis berdasarkan pemiliknya.</p>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </section>
            <?php endif; ?>

            <section class="panel files-panel" aria-labelledby="files-title" id="files">
                <div class="panel-head">
                    <div>
                        <h2 id="files-title"><?= $currentUser['role'] === 'admin' ? 'Kelola Semua File' : 'Tabel Cloud Saya'; ?></h2>
                        <span><?= count($files); ?> berkas tersimpan</span>
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
                                    <th>Pemilik</th>
                                    <?php if (in_array($currentUser['role'], ['admin', 'user'], true)): ?>
                                        <th>Tabel Viewer/Guest</th>
                                    <?php endif; ?>
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
                                                    <?php else: 
                                                        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                                                        $iconClass = 'fa-file';
                                                        $iconColor = '#94a3b8';
                                                        if (in_array($ext, ['pdf'])) { $iconClass = 'fa-file-pdf'; $iconColor = '#ef4444'; }
                                                        elseif (in_array($ext, ['zip', 'rar', 'tar', 'gz'])) { $iconClass = 'fa-file-zipper'; $iconColor = '#eab308'; }
                                                        elseif (in_array($ext, ['doc', 'docx', 'txt', 'rtf'])) { $iconClass = 'fa-file-word'; $iconColor = '#10b981'; }
                                                    ?>
                                                        <div class="file-icon" aria-hidden="true" style="background: <?= $iconColor ?>15; color: <?= $iconColor ?>;">
                                                            <i class="fa-solid <?= $iconClass ?>"></i>
                                                        </div>
                                                    <?php endif; ?>
                                                    <span title="<?= htmlspecialchars($file['name']); ?>"><?= htmlspecialchars($file['name']); ?></span>
                                                </div>
                                                <div class="actions file-actions">
                                                    <a class="action-button download icon-only" href="download.php?file=<?= urlencode($file['name']); ?>" aria-label="Unduh <?= htmlspecialchars($file['name']); ?>" title="Unduh">
                                                        <i class="fa-solid fa-download" aria-hidden="true"></i>
                                                    </a>
                                                    <?php if (authCanDeleteFile($currentUser, $file)): ?>
                                                        <form class="action-form" action="delete.php" method="post" onsubmit="return confirm('Hapus berkas ini?');">
                                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                                                            <input type="hidden" name="file" value="<?= htmlspecialchars($file['name'], ENT_QUOTES, 'UTF-8'); ?>">
                                                            <button class="action-button delete icon-only" type="submit" aria-label="Hapus <?= htmlspecialchars($file['name']); ?>" title="Hapus">
                                                                <i class="fa-solid fa-trash-can" aria-hidden="true"></i>
                                                            </button>
                                                        </form>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="meta" data-label="Ukuran"><?= formatFileSize((int) $file['size']); ?></td>
                                        <td class="meta" data-label="Terakhir Diubah"><?= formatTimestamp((int) $file['modified']); ?></td>
                                        <td class="meta" data-label="Pemilik"><?= htmlspecialchars((string) $file['owner'], ENT_QUOTES, 'UTF-8'); ?></td>
                                        <?php if (in_array($currentUser['role'], ['admin', 'user'], true)): ?>
                                            <td class="meta" data-label="Tabel Viewer/Guest">
                                                <form class="access-form" action="access.php" method="post" data-ajax="true">
                                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                                                    <input type="hidden" name="file" value="<?= htmlspecialchars($file['name'], ENT_QUOTES, 'UTF-8'); ?>">
                                                    <label class="toggle-switch">
                                                        <input type="checkbox" name="viewer_access" <?= (bool) $file['viewerAccess'] ? 'checked' : ''; ?>>
                                                        <span class="toggle-slider"></span>
                                                        <span class="toggle-label">Viewer</span>
                                                    </label>
                                                    <label class="toggle-switch">
                                                        <input type="checkbox" name="guest_access" <?= (bool) $file['guestAccess'] ? 'checked' : ''; ?>>
                                                        <span class="toggle-slider"></span>
                                                        <span class="toggle-label">Guest</span>
                                                    </label>
                                                    <button class="action-button fallback-submit" type="submit">Simpan</button>
                                                </form>
                                            </td>
                                        <?php endif; ?>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <div class="empty-card">
                            <div class="empty-svg">
                                <svg width="120" height="120" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" style="color: #c7d2fe; filter: drop-shadow(0 4px 12px rgba(99, 102, 241, 0.2));">
                                    <path d="M12 22C17.5228 22 22 17.5228 22 12C22 6.47715 17.5228 2 12 2C6.47715 2 2 6.47715 2 12C2 17.5228 6.47715 22 12 22Z"/>
                                    <path d="M12 16V8"/>
                                    <path d="M8 12L12 8L16 12"/>
                                    <path d="M4.93 4.93L19.07 19.07" stroke-dasharray="2 4"/>
                                </svg>
                            </div>
                            <h3>Belum ada berkas</h3>
                            <p>Mulai simpan file Anda di hawpiwcloud. Seret dan lepaskan file di area unggahan di atas untuk memulainya.</p>
                            <p>Penyimpanan Anda masih kosong. Unggah berkas untuk menampilkan daftar dan mulai mengelolanya dari dashboard ini.</p>
                        </div>
                    </div>
                <?php endif; ?>
            </section>
            </div>
        </main>
    </div>
    <script src="assets/app.js" defer></script>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const toggle = document.getElementById('mobile-toggle');
            const sidebar = document.getElementById('sidebar');
            
            if(toggle && sidebar) {
                toggle.addEventListener('click', () => {
                    sidebar.classList.toggle('is-open');
                });
                
                // Tutup sidebar jika ngeklik di luar
                document.addEventListener('click', (e) => {
                    if(sidebar.classList.contains('is-open') && !sidebar.contains(e.target) && !toggle.contains(e.target)) {
                        sidebar.classList.remove('is-open');
                    }
                });
            }
        });
    </script>
</body>

</html>
