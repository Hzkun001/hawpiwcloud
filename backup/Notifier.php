<?php

declare(strict_types=1);

final class BackupNotifier
{
    private $config;
    private $logger;

    public function __construct(BackupConfig $config, BackupLogger $logger)
    {
        $this->config = $config;
        $this->logger = $logger;
    }

    public function send(string $subject, string $message): void
    {
        $email = $this->config->notifications['email'] ?? '';
        if (is_string($email) && $email !== '') {
            $this->attempt('email', static function () use ($email, $subject, $message): bool {
                return mail($email, $subject, $message);
            });
        }

        $telegramToken = $this->config->notifications['telegram_token'] ?? '';
        $telegramChat = $this->config->notifications['telegram_chat_id'] ?? '';
        if (is_string($telegramToken) && $telegramToken !== '' && is_string($telegramChat) && $telegramChat !== '') {
            $this->attempt('telegram', function () use ($telegramToken, $telegramChat, $subject, $message): bool {
                return $this->postJson(
                    'https://api.telegram.org/bot' . rawurlencode($telegramToken) . '/sendMessage',
                    ['chat_id' => $telegramChat, 'text' => $subject . PHP_EOL . $message]
                );
            });
        }

        $discord = $this->config->notifications['discord_webhook'] ?? '';
        if (is_string($discord) && $discord !== '') {
            $this->attempt('discord', function () use ($discord, $subject, $message): bool {
                return $this->postJson($discord, ['content' => '**' . $subject . '**' . PHP_EOL . $message]);
            });
        }
    }

    private function attempt(string $channel, callable $sender): void
    {
        try {
            if (!$sender()) {
                throw new RuntimeException('Provider menolak notifikasi.');
            }
            $this->logger->info('notification.sent', ['channel' => $channel]);
        } catch (Throwable $exception) {
            $this->logger->error('notification.failed', ['channel' => $channel, 'error' => $exception->getMessage()]);
        }
    }

    private function postJson(string $url, array $payload): bool
    {
        $body = json_encode($payload, JSON_UNESCAPED_SLASHES);
        if (!is_string($body)) {
            return false;
        }

        $context = stream_context_create(['http' => [
            'method' => 'POST',
            'header' => "Content-Type: application/json\r\n",
            'content' => $body,
            'timeout' => 10,
            'ignore_errors' => true,
        ]]);
        $result = @file_get_contents($url, false, $context);

        return $result !== false;
    }
}
