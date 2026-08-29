<?php

declare(strict_types=1);

function dashboardRenderBanner(?array $banner): void
{
    if ($banner === null) {
        return;
    }
    ?>
    <div class="banner <?= htmlspecialchars((string) $banner['type'], ENT_QUOTES, 'UTF-8'); ?>" role="status" aria-live="polite">
        <div class="banner-badge">
            <?php if ($banner['type'] === 'success'): ?>
                <i class="fa-solid fa-circle-check" aria-hidden="true"></i>
            <?php else: ?>
                <i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i>
            <?php endif; ?>
        </div>
        <div>
            <strong><?= htmlspecialchars((string) $banner['title'], ENT_QUOTES, 'UTF-8'); ?></strong>
            <p><?= htmlspecialchars((string) $banner['message'], ENT_QUOTES, 'UTF-8'); ?></p>
        </div>
    </div>
    <?php
}

function dashboardFileIconFor(array $file): array
{
    $extension = strtolower(pathinfo((string) $file['name'], PATHINFO_EXTENSION));
    $iconClass = 'fa-file';
    $iconColor = '#94a3b8';

    if (!empty($file['isImage'])) {
        return ['fa-image', '#3b82f6'];
    }
    if (in_array($extension, ['pdf'], true)) {
        return ['fa-file-pdf', '#ef4444'];
    }
    if (in_array($extension, ['zip', 'rar', 'tar', 'gz'], true)) {
        return ['fa-file-zipper', '#eab308'];
    }
    if (in_array($extension, ['doc', 'docx', 'txt', 'rtf'], true)) {
        return ['fa-file-word', '#10b981'];
    }

    return [$iconClass, $iconColor];
}

function dashboardRenderOverview(array $context): void
{
    extract($context);
    ?>
    <div class="dashboard-widgets" id="overview">
        <div class="widget-card">
            <div class="widget-icon widget-icon--storage">
                <i class="fa-solid fa-hard-drive"></i>
            </div>
            <div class="widget-info">
                <span class="widget-label">Kapasitas Terpakai</span>
                <strong class="widget-value"><?= storageFormatFileSize((int) $totalStorageBytes); ?> / 1 GB</strong>
                <div class="progress-bar-bg">
                    <div class="progress-bar-fill" style="width: <?= htmlspecialchars((string) $storagePercentage, ENT_QUOTES, 'UTF-8'); ?>%;"></div>
                </div>
            </div>
        </div>

        <div class="widget-card">
            <div class="widget-icon widget-icon--files">
                <i class="fa-solid fa-file-lines"></i>
            </div>
            <div class="widget-info">
                <span class="widget-label">Total Berkas</span>
                <strong class="widget-value"><?= count($userFiles); ?> Berkas</strong>
                <span class="widget-subtext">Termasuk <?= (int) $imageCount; ?> gambar. Terbaru: <?= htmlspecialchars((string) $latestLabel, ENT_QUOTES, 'UTF-8'); ?></span>
            </div>
        </div>

        <div class="widget-card recent-activity-widget">
            <div class="widget-icon widget-icon--activity">
                <i class="fa-solid fa-clock-rotate-left"></i>
            </div>
            <div class="widget-info">
                <span class="widget-label">Aktivitas Terbaru</span>
                <?php if ($recentFiles !== []): ?>
                    <div class="recent-files-mini">
                        <?php foreach ($recentFiles as $recentFile): ?>
                            <?php [$iconClass, $iconColor] = dashboardFileIconFor($recentFile); ?>
                            <div class="recent-file-item" title="<?= htmlspecialchars((string) $recentFile['name'], ENT_QUOTES, 'UTF-8'); ?>">
                                <i class="fa-solid <?= htmlspecialchars($iconClass, ENT_QUOTES, 'UTF-8'); ?>" style="color: <?= htmlspecialchars($iconColor, ENT_QUOTES, 'UTF-8'); ?>;"></i>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <span class="widget-subtext">Belum ada aktivitas</span>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php if ($currentUser['role'] === 'admin'): ?>
        <section class="panel" id="backup-status" aria-labelledby="backup-status-title">
            <div class="panel-head">
                <div>
                    <h2 id="backup-status-title">Status Backup</h2>
                    <span>Backup terakhir: <?= htmlspecialchars((string) $lastBackupTime, ENT_QUOTES, 'UTF-8'); ?></span>
                </div>
                <strong><?= htmlspecialchars(strtoupper((string) $lastBackupStatus), ENT_QUOTES, 'UTF-8'); ?></strong>
            </div>
        </section>
    <?php endif; ?>
    <?php
}

function dashboardRenderUpload(array $context): void
{
    extract($context);
    ?>
    <section class="panel" id="upload-panel" aria-labelledby="upload-title">
        <div class="panel-head">
            <div>
                <h2 id="upload-title">Unggah Berkas</h2>
                <span>Gunakan panel ini untuk menambah file baru. Anda dapat mengatur akses agar file ini dapat dilihat oleh Viewer.</span>
            </div>
        </div>

        <div class="upload-card">
            <form action="upload.php" method="post" enctype="multipart/form-data" id="upload-form">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars((string) $csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                <div class="upload-grid">
                    <label class="dropzone" id="dropzone" for="file-input">
                        <input class="dropzone-input" id="file-input" type="file" name="fileToUpload" accept="<?= htmlspecialchars((string) $allowedAcceptAttribute, ENT_QUOTES, 'UTF-8'); ?>" data-max-file-bytes="<?= htmlspecialchars((string) $effectiveUploadLimitBytes, ENT_QUOTES, 'UTF-8'); ?>" data-max-file-label="<?= htmlspecialchars((string) $effectiveUploadLimitLabel, ENT_QUOTES, 'UTF-8'); ?>" data-allowed-file-types="<?= htmlspecialchars((string) $allowedTypeLabel, ENT_QUOTES, 'UTF-8'); ?>" required>
                        <div class="dropzone-content" id="dropzone-content">
                            <div class="dropzone-icon" aria-hidden="true">
                                <i class="fa-solid fa-cloud-arrow-up"></i>
                            </div>
                            <div class="dropzone-title">Klik untuk mengunggah atau seret dan lepaskan</div>
                            <p class="dropzone-copy">Format yang diizinkan: <?= htmlspecialchars((string) $allowedTypeLabel, ENT_QUOTES, 'UTF-8'); ?>. Pratinjau akan tampil otomatis untuk gambar.</p>
                            <span class="file-chip" id="file-chip">Belum ada berkas yang dipilih</span>
                        </div>
                    </label>

                    <aside class="preview-panel" aria-live="polite">
                        <div class="preview-titlebar">
                            <div class="window-dots" aria-hidden="true"><span></span><span></span><span></span></div>
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
                            <div class="access-options" aria-label="Akses khusus">
                                <label><input type="checkbox" name="viewer_access" checked><span>Masukkan ke tabel Viewer</span></label>
                            </div>
                            <div class="helper-note">
                                <span><strong>Tips:</strong> Gambar akan menampilkan thumbnail secara otomatis.</span>
                                <span>Batas: <?= htmlspecialchars((string) $effectiveUploadLimitLabel, ENT_QUOTES, 'UTF-8'); ?>.</span>
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
    <?php
}

function dashboardRenderUsers(array $context): void
{
    extract($context);
    ?>
    <section class="panel files-panel" aria-labelledby="users-title" id="users">
        <div class="panel-head">
            <div>
                <h2 id="users-title">Manajemen User</h2>
                <span><?= count($accounts); ?> akun terdaftar</span>
            </div>
        </div>

        <div class="user-management">
            <form class="user-create-card" action="users.php" method="post">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars((string) $csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" name="action" value="create">
                <div class="user-create-head">
                    <h3>Buat User Baru</h3>
                    <p>Admin dapat menambahkan akun untuk kebutuhan tabel cloud masing-masing pengguna.</p>
                </div>
                <label><span>Nama Lengkap</span><input type="text" name="name" placeholder="Contoh: Hafidz" required></label>
                <label><span>Username</span><input type="text" name="username" placeholder="huruf_angka" pattern="[A-Za-z0-9_-]{3,32}" required></label>
                <label><span>Password</span><input type="password" name="password" minlength="6" placeholder="Minimal 6 karakter" required></label>
                <label><span>Level</span><select name="role" required><option value="user">User</option><option value="viewer">Viewer</option><option value="admin">Admin</option></select></label>
                <button class="primary-button" type="submit">Tambah User</button>
            </form>
        </div>

        <div class="table-wrap">
            <table>
                <thead><tr><th>Username</th><th>Nama</th><th>Level</th><th>File</th><th>Kewenangan Utama</th><th>Aksi</th></tr></thead>
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
                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars((string) $csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
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
                </tbody>
            </table>
        </div>
    </section>
    <?php
}

function dashboardRenderOwnerTables(array $context): void
{
    extract($context);
    if ($currentUser['role'] !== 'admin') {
        return;
    }
    ?>
    <section class="panel files-panel" aria-labelledby="owner-files-title" id="owner-files">
        <div class="panel-head">
            <div>
                <h2 id="owner-files-title">Tabel Penyimpanan Per User</h2>
                <span>Admin melihat setiap tabel penyimpanan berdasarkan pemilik file</span>
            </div>
        </div>
        <div class="owner-table-stack">
            <?php foreach ($filesByOwner as $owner => $ownerFiles): ?>
                <section class="owner-table" aria-labelledby="owner-title-<?= htmlspecialchars((string) $owner, ENT_QUOTES, 'UTF-8'); ?>">
                    <div class="owner-table-head">
                        <h3 id="owner-title-<?= htmlspecialchars((string) $owner, ENT_QUOTES, 'UTF-8'); ?>">
                            <?= htmlspecialchars((string) $owner, ENT_QUOTES, 'UTF-8'); ?>
                            <?= !isset($accounts[$owner]) ? ' <span class="status-pill status-pill--deleted">(Dihapus)</span>' : ''; ?>
                        </h3>
                        <span><?= count($ownerFiles); ?> file</span>
                    </div>
                    <div class="table-wrap">
                        <table>
                            <thead><tr><th>Nama Berkas</th><th>Ukuran</th><th>Terakhir Diubah</th></tr></thead>
                            <tbody>
                                <?php foreach ($ownerFiles as $file): ?>
                                    <tr>
                                        <td data-label="Nama Berkas"><?= htmlspecialchars((string) $file['name'], ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td class="meta" data-label="Ukuran"><?= storageFormatFileSize((int) $file['size']); ?></td>
                                        <td class="meta" data-label="Terakhir Diubah"><?= storageFormatTimestamp((int) $file['modified']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </section>
            <?php endforeach; ?>
        </div>
    </section>
    <?php
}

function dashboardRenderFiles(array $context): void
{
    extract($context);
    ?>
    <section class="panel files-panel" aria-labelledby="files-title" id="files">
        <div class="panel-head">
            <div>
                <h2 id="files-title"><?= $currentUser['role'] === 'admin' ? 'Kelola Semua File' : 'Tabel Cloud Saya'; ?></h2>
                <span><?= count($files); ?> berkas tersimpan</span>
            </div>
        </div>

        <?php if ($files !== []): ?>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Nama Berkas</th><th>Ukuran</th><th>Terakhir Diubah</th><th>Pemilik</th>
                            <th>Tabel Viewer</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($files as $file): ?>
                            <tr>
                                <td data-label="Nama Berkas">
                                    <div class="file-row">
                                        <div class="file-name">
                                            <?php if ($file['isImage']): ?>
                                                <img class="file-preview" src="uploads/<?= htmlspecialchars(rawurlencode((string) $file['name']), ENT_QUOTES, 'UTF-8'); ?>" alt="Pratinjau <?= htmlspecialchars((string) $file['name'], ENT_QUOTES, 'UTF-8'); ?>" loading="lazy">
                                            <?php else: ?>
                                                <?php [$iconClass, $iconColor] = dashboardFileIconFor($file); ?>
                                                <div class="file-icon" aria-hidden="true" style="background: <?= htmlspecialchars($iconColor, ENT_QUOTES, 'UTF-8'); ?>15; color: <?= htmlspecialchars($iconColor, ENT_QUOTES, 'UTF-8'); ?>;">
                                                    <i class="fa-solid <?= htmlspecialchars($iconClass, ENT_QUOTES, 'UTF-8'); ?>"></i>
                                                </div>
                                            <?php endif; ?>
                                            <span title="<?= htmlspecialchars((string) $file['name'], ENT_QUOTES, 'UTF-8'); ?>"><?= htmlspecialchars((string) $file['name'], ENT_QUOTES, 'UTF-8'); ?></span>
                                        </div>
                                        <div class="actions file-actions">
                                            <a class="action-button download icon-only" href="download.php?file=<?= urlencode((string) $file['name']); ?>" aria-label="Unduh <?= htmlspecialchars((string) $file['name'], ENT_QUOTES, 'UTF-8'); ?>" title="Unduh">
                                                <i class="fa-solid fa-download" aria-hidden="true"></i>
                                            </a>
                                            <?php if (authCanDeleteFile($currentUser, $file)): ?>
                                                <form class="action-form" action="delete.php" method="post" onsubmit="return confirm('Hapus berkas ini?');">
                                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars((string) $csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                                                    <input type="hidden" name="file" value="<?= htmlspecialchars((string) $file['name'], ENT_QUOTES, 'UTF-8'); ?>">
                                                    <button class="action-button delete icon-only" type="submit" aria-label="Hapus <?= htmlspecialchars((string) $file['name'], ENT_QUOTES, 'UTF-8'); ?>" title="Hapus">
                                                        <i class="fa-solid fa-trash-can" aria-hidden="true"></i>
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>
                                <td class="meta" data-label="Ukuran"><?= storageFormatFileSize((int) $file['size']); ?></td>
                                <td class="meta" data-label="Terakhir Diubah"><?= storageFormatTimestamp((int) $file['modified']); ?></td>
                                <td class="meta" data-label="Pemilik"><?= htmlspecialchars((string) $file['owner'], ENT_QUOTES, 'UTF-8'); ?></td>
                                <td class="meta" data-label="Tabel Viewer">
                                    <form class="access-form" action="access.php" method="post" data-ajax="true">
                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars((string) $csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                                        <input type="hidden" name="file" value="<?= htmlspecialchars((string) $file['name'], ENT_QUOTES, 'UTF-8'); ?>">
                                        <label class="toggle-switch"><input type="checkbox" name="viewer_access" <?= (bool) $file['viewerAccess'] ? 'checked' : ''; ?>><span class="toggle-slider"></span><span class="toggle-label">Viewer</span></label>
                                        <button class="action-button fallback-submit" type="submit">Simpan</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <div class="empty-card">
                    <div class="empty-svg">
                        <svg width="120" height="120" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M12 22C17.5228 22 22 17.5228 22 12C22 6.47715 17.5228 2 12 2C6.47715 2 2 6.47715 2 12C2 17.5228 6.47715 22 12 22Z"/>
                            <path d="M12 16V8"/><path d="M8 12L12 8L16 12"/><path d="M4.93 4.93L19.07 19.07" stroke-dasharray="2 4"/>
                        </svg>
                    </div>
                    <h3>Belum ada berkas</h3>
                    <p>Penyimpanan Anda masih kosong. Unggah berkas untuk menampilkan daftar dan mulai mengelolanya.</p>
                </div>
            </div>
        <?php endif; ?>
    </section>
    <?php
}
