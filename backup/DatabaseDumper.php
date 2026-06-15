<?php

declare(strict_types=1);

final class BackupDatabaseDumper
{
    private $config;

    public function __construct(BackupConfig $config)
    {
        $this->config = $config;
    }

    public function dump(string $destination, array $applicationState): void
    {
        if ($this->config->databaseDriver === 'json') {
            $this->dumpApplicationState($destination, $applicationState);
            return;
        }

        if (!$this->config->shellAvailable()) {
            throw new RuntimeException('Hosting membatasi eksekusi command eksternal. Gunakan BACKUP_DATABASE_DRIVER=json untuk mode web-friendly.');
        }

        if ($this->config->databaseDriver === 'mysql') {
            $this->dumpMysql($destination);
            return;
        }
        if (in_array($this->config->databaseDriver, ['pgsql', 'postgres', 'postgresql'], true)) {
            $this->dumpPostgres($destination);
            return;
        }
        if ($this->config->databaseDriver === 'sqlite') {
            $this->dumpSqlite($destination);
            return;
        }

        throw new RuntimeException('Driver database backup tidak didukung: ' . $this->config->databaseDriver);
    }

    public function restore(string $source): void
    {
        if ($this->config->databaseDriver === 'json') {
            return;
        }

        if (!$this->config->shellAvailable()) {
            throw new RuntimeException('Hosting membatasi eksekusi command eksternal. Restore database non-JSON tidak dapat dijalankan di hosting ini.');
        }

        if ($this->config->databaseDriver === 'mysql') {
            $this->restoreMysql($source);
            return;
        }
        if (in_array($this->config->databaseDriver, ['pgsql', 'postgres', 'postgresql'], true)) {
            $this->restorePostgres($source);
            return;
        }
        if ($this->config->databaseDriver === 'sqlite') {
            $this->restoreSqlite($source);
            return;
        }

        throw new RuntimeException('Driver database restore tidak didukung: ' . $this->config->databaseDriver);
    }

    private function dumpApplicationState(string $destination, array $state): void
    {
        $lines = [
            '-- hawpiwcloud portable application state dump',
            '-- Restore through: php bin/restore.php --archive=/path/to/backup.zip --yes',
            'CREATE TABLE IF NOT EXISTS hawpiwcloud_state (state_key VARCHAR(100) PRIMARY KEY, value_json TEXT NOT NULL);',
            'DELETE FROM hawpiwcloud_state;',
        ];

        foreach ($state as $key => $value) {
            $json = json_encode($value, JSON_UNESCAPED_SLASHES);
            if (!is_string($json)) {
                throw new RuntimeException('Tidak dapat encode state aplikasi.');
            }
            $lines[] = sprintf(
                "INSERT INTO hawpiwcloud_state (state_key, value_json) VALUES ('%s', '%s');",
                str_replace("'", "''", (string) $key),
                str_replace("'", "''", $json)
            );
        }

        if (file_put_contents($destination, implode(PHP_EOL, $lines) . PHP_EOL, LOCK_EX) === false) {
            throw new RuntimeException('Tidak dapat menulis dump database.');
        }
    }

    private function dumpMysql(string $destination): void
    {
        $database = $this->required('name');
        $command = ['mysqldump', '--single-transaction', '--quick', '--routines', '--events'];
        $command = array_merge($command, $this->mysqlConnectionArgs(), [$database]);
        BackupProcess::run($command, $destination, ['MYSQL_PWD' => $this->config->database['password']]);
    }

    private function dumpPostgres(string $destination): void
    {
        $command = array_merge(['pg_dump', '--format=p', '--file=' . $destination], $this->postgresConnectionArgs(), [$this->required('name')]);
        BackupProcess::run($command, null, ['PGPASSWORD' => $this->config->database['password']]);
    }

    private function dumpSqlite(string $destination): void
    {
        BackupProcess::run(['sqlite3', $this->required('path'), '.dump'], $destination);
    }

    private function restoreMysql(string $source): void
    {
        $command = array_merge(['mysql'], $this->mysqlConnectionArgs(), [$this->required('name')]);
        BackupProcess::run($command, null, ['MYSQL_PWD' => $this->config->database['password']], $source);
    }

    private function restorePostgres(string $source): void
    {
        $command = array_merge(['psql'], $this->postgresConnectionArgs(), [$this->required('name')]);
        BackupProcess::run($command, null, ['PGPASSWORD' => $this->config->database['password']], $source);
    }

    private function restoreSqlite(string $source): void
    {
        BackupProcess::run(['sqlite3', $this->required('path')], null, [], $source);
    }

    private function mysqlConnectionArgs(): array
    {
        $args = ['--host=' . $this->config->database['host']];
        if ($this->config->database['port'] !== '') {
            $args[] = '--port=' . $this->config->database['port'];
        }
        if ($this->config->database['user'] !== '') {
            $args[] = '--user=' . $this->config->database['user'];
        }

        return $args;
    }

    private function postgresConnectionArgs(): array
    {
        $args = ['--host=' . $this->config->database['host']];
        if ($this->config->database['port'] !== '') {
            $args[] = '--port=' . $this->config->database['port'];
        }
        if ($this->config->database['user'] !== '') {
            $args[] = '--username=' . $this->config->database['user'];
        }

        return $args;
    }

    private function required(string $key): string
    {
        $value = $this->config->database[$key] ?? '';
        if (!is_string($value) || $value === '') {
            throw new RuntimeException('Konfigurasi database wajib diisi: BACKUP_DATABASE_' . strtoupper($key));
        }

        return $value;
    }
}
