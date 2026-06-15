<?php

declare(strict_types=1);

if (!defined('HAWPIWCLOUD_BACKUP_BOOTSTRAP_VERSION')) {
    define('HAWPIWCLOUD_BACKUP_BOOTSTRAP_VERSION', '2026-06-15-1');
}

require_once __DIR__ . DIRECTORY_SEPARATOR . 'Config.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'Logger.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'Process.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'Archive.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'Crypto.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'DatabaseDumper.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'OffsiteReplicator.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'Notifier.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'BackupManager.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'TrashManager.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'VersionManager.php';
