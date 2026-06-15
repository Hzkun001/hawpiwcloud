<?php

declare(strict_types=1);

final class BackupConfig
{
    public $appRoot;
    public $backupRoot;
    public $trashDir;
    public $logPath;
    public $statusPath;
    public $catalogPath;
    public $auditPath;
    public $inventoryPath;
    public $versionsDir;
    public $databaseDriver;
    public $database;
    public $offsiteDriver;
    public $offsiteTarget;
    public $offsiteTargets;
    public $requireOffsite;
    public $compressionFormat;
    public $compressionLevel;
    public $encryptionPassword;
    public $retention;
    public $capacityWarningPercent;
    public $notifications;

    public function __construct(
        string $appRoot,
        string $backupRoot,
        string $trashDir,
        string $logPath,
        string $statusPath,
        string $catalogPath,
        string $auditPath,
        string $inventoryPath,
        string $versionsDir,
        string $databaseDriver,
        array $database,
        string $offsiteDriver,
        string $offsiteTarget,
        array $offsiteTargets,
        bool $requireOffsite,
        string $compressionFormat,
        int $compressionLevel,
        string $encryptionPassword,
        array $retention,
        int $capacityWarningPercent,
        array $notifications
    ) {
        $this->appRoot = $appRoot;
        $this->backupRoot = $backupRoot;
        $this->trashDir = $trashDir;
        $this->logPath = $logPath;
        $this->statusPath = $statusPath;
        $this->catalogPath = $catalogPath;
        $this->auditPath = $auditPath;
        $this->inventoryPath = $inventoryPath;
        $this->versionsDir = $versionsDir;
        $this->databaseDriver = $databaseDriver;
        $this->database = $database;
        $this->offsiteDriver = $offsiteDriver;
        $this->offsiteTarget = $offsiteTarget;
        $this->offsiteTargets = $offsiteTargets;
        $this->requireOffsite = $requireOffsite;
        $this->compressionFormat = $compressionFormat;
        $this->compressionLevel = $compressionLevel;
        $this->encryptionPassword = $encryptionPassword;
        $this->retention = $retention;
        $this->capacityWarningPercent = $capacityWarningPercent;
        $this->notifications = $notifications;
    }

    public static function fromEnvironment(?string $appRoot = null): self
    {
        $root = rtrim($appRoot ?? dirname(__DIR__), DIRECTORY_SEPARATOR);
        $backupRoot = self::env('BACKUP_LOCAL_DIR', dirname($root) . DIRECTORY_SEPARATOR . 'hawpiwcloud-backups');

        return new self(
            $root,
            rtrim($backupRoot, DIRECTORY_SEPARATOR),
            rtrim(self::env('TRASH_DIR', $root . DIRECTORY_SEPARATOR . 'trash'), DIRECTORY_SEPARATOR),
            self::env('BACKUP_LOG_PATH', $backupRoot . DIRECTORY_SEPARATOR . 'backup.log'),
            self::env('BACKUP_STATUS_PATH', $backupRoot . DIRECTORY_SEPARATOR . 'status.json'),
            self::env('BACKUP_CATALOG_PATH', $backupRoot . DIRECTORY_SEPARATOR . 'catalog.json'),
            self::env('BACKUP_AUDIT_PATH', $backupRoot . DIRECTORY_SEPARATOR . 'audit.log'),
            self::env('BACKUP_INVENTORY_PATH', $backupRoot . DIRECTORY_SEPARATOR . 'inventory.json'),
            rtrim(self::env('FILE_VERSIONS_DIR', dirname($root) . DIRECTORY_SEPARATOR . 'hawpiwcloud-versions'), DIRECTORY_SEPARATOR),
            strtolower(self::env('BACKUP_DATABASE_DRIVER', 'json')),
            [
                'host' => self::env('BACKUP_DATABASE_HOST', '127.0.0.1'),
                'port' => self::env('BACKUP_DATABASE_PORT', ''),
                'name' => self::env('BACKUP_DATABASE_NAME', ''),
                'user' => self::env('BACKUP_DATABASE_USER', ''),
                'password' => self::env('BACKUP_DATABASE_PASSWORD', ''),
                'path' => self::env('BACKUP_DATABASE_PATH', ''),
            ],
            strtolower(self::env('BACKUP_OFFSITE_DRIVER', 'none')),
            self::env('BACKUP_OFFSITE_TARGET', ''),
            self::offsiteTargets(),
            self::boolEnv('BACKUP_REQUIRE_OFFSITE', false),
            strtolower(self::env('BACKUP_COMPRESSION_FORMAT', 'zip')),
            self::intEnv('BACKUP_COMPRESSION_LEVEL', 6, 0, 9),
            self::env('BACKUP_ENCRYPTION_PASSWORD', ''),
            [
                'daily_days' => self::intEnv('BACKUP_RETENTION_DAILY_DAYS', 30, 1, 3650),
                'weekly_days' => self::intEnv('BACKUP_RETENTION_WEEKLY_DAYS', 90, 1, 3650),
                'monthly_days' => self::intEnv('BACKUP_RETENTION_MONTHLY_DAYS', 365, 1, 3650),
            ],
            self::intEnv('BACKUP_CAPACITY_WARNING_PERCENT', 85, 1, 100),
            [
                'email' => self::env('BACKUP_NOTIFY_EMAIL', ''),
                'telegram_token' => self::env('BACKUP_TELEGRAM_BOT_TOKEN', ''),
                'telegram_chat_id' => self::env('BACKUP_TELEGRAM_CHAT_ID', ''),
                'discord_webhook' => self::env('BACKUP_DISCORD_WEBHOOK', ''),
            ],
        );
    }

    public function ensureDirectories(): void
    {
        foreach ([$this->backupRoot, dirname($this->logPath), dirname($this->statusPath), dirname($this->catalogPath), dirname($this->auditPath), dirname($this->inventoryPath), $this->trashDir, $this->versionsDir] as $directory) {
            if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
                throw new RuntimeException('Tidak dapat membuat direktori: ' . $directory);
            }
        }
    }

    public function shellAvailable(): bool
    {
        return BackupProcess::isAvailable();
    }

    public function webFriendlyMode(): bool
    {
        return strtolower($this->compressionFormat) === 'zip'
            && $this->offsiteDriver === 'local'
            && $this->databaseDriver === 'json'
            && $this->encryptionPassword === '';
    }

    private static function env(string $name, string $default): string
    {
        $value = getenv($name);

        return is_string($value) && $value !== '' ? $value : $default;
    }

    private static function boolEnv(string $name, bool $default): bool
    {
        $value = getenv($name);
        if (!is_string($value) || $value === '') {
            return $default;
        }

        return filter_var($value, FILTER_VALIDATE_BOOL);
    }

    private static function intEnv(string $name, int $default, int $minimum, int $maximum): int
    {
        $value = getenv($name);
        $parsed = is_string($value) && $value !== '' ? (int) $value : $default;

        return max($minimum, min($maximum, $parsed));
    }

    private static function offsiteTargets(): array
    {
        $json = getenv('BACKUP_OFFSITE_TARGETS_JSON');
        if (!is_string($json) || trim($json) === '') {
            return [];
        }

        $targets = json_decode($json, true);

        return is_array($targets) ? array_values(array_filter($targets, 'is_array')) : [];
    }
}
