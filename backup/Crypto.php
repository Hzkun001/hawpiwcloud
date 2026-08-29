<?php

declare(strict_types=1);

final class BackupCrypto
{
    private $config;

    public function __construct(BackupConfig $config)
    {
        $this->config = $config;
    }

    public function enabled(): bool
    {
        return $this->config->encryptionPassword !== '';
    }

    public function encrypt(string $source): string
    {
        if (!$this->enabled()) {
            return $source;
        }

        $destination = $source . '.enc';
        BackupProcess::run(
            ['openssl', 'enc', '-aes-256-cbc', '-salt', '-pbkdf2', '-iter', '200000', '-in', $source, '-out', $destination, '-pass', 'env:BACKUP_ENCRYPTION_PASSWORD'],
            null,
            ['BACKUP_ENCRYPTION_PASSWORD' => $this->config->encryptionPassword]
        );
        if (!unlink($source)) {
            throw new RuntimeException('File plaintext tidak dapat dihapus setelah enkripsi.');
        }

        return $destination;
    }

    public function decrypt(string $source, string $destination): string
    {
        if (substr($source, -4) !== '.enc') {
            return $source;
        }
        if (!$this->enabled()) {
            throw new RuntimeException('BACKUP_ENCRYPTION_PASSWORD wajib diisi untuk restore encrypted backup.');
        }

        BackupProcess::run(
            ['openssl', 'enc', '-d', '-aes-256-cbc', '-pbkdf2', '-iter', '200000', '-in', $source, '-out', $destination, '-pass', 'env:BACKUP_ENCRYPTION_PASSWORD'],
            null,
            ['BACKUP_ENCRYPTION_PASSWORD' => $this->config->encryptionPassword]
        );

        return $destination;
    }
}
