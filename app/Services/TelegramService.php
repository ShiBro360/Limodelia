<?php
declare(strict_types=1);

namespace App\Services;

use RuntimeException;

final class TelegramService
{
    private array $settings;

    public function __construct(private readonly SettingsService $settingsService)
    {
        $this->settings = $this->settingsService->telegram();
    }

    public function isConfigured(): bool
    {
        return (bool) ($this->settings['enabled'] ?? false)
            && trim((string) ($this->settings['bot_token'] ?? '')) !== ''
            && trim((string) ($this->settings['chat_id'] ?? '')) !== '';
    }

    public function chatId(): string
    {
        return (string) ($this->settings['chat_id'] ?? '');
    }

    public function sendMessage(string $text, array $replyMarkup = []): void
    {
        if (!$this->isConfigured()) {
            return;
        }

        $this->sendMessageToChat($this->chatId(), $text, $replyMarkup);
    }

    public function sendMessageToChat(string $chatId, string $text, array $replyMarkup = []): array
    {
        $payload = [
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => 'HTML',
            'disable_web_page_preview' => true,
        ];

        if ($replyMarkup !== []) {
            $payload['reply_markup'] = json_encode($replyMarkup, JSON_UNESCAPED_UNICODE);
        }

        return $this->request('sendMessage', $payload);
    }

    public function editMessageText(string $chatId, int $messageId, string $text, array $replyMarkup = []): void
    {
        $payload = [
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'text' => $text,
            'parse_mode' => 'HTML',
            'disable_web_page_preview' => true,
        ];

        if ($replyMarkup !== []) {
            $payload['reply_markup'] = json_encode($replyMarkup, JSON_UNESCAPED_UNICODE);
        }

        $this->request('editMessageText', $payload);
    }

    public function answerCallbackQuery(string $callbackQueryId, string $text = ''): void
    {
        $payload = ['callback_query_id' => $callbackQueryId];
        if ($text !== '') {
            $payload['text'] = $text;
        }

        $this->request('answerCallbackQuery', $payload);
    }

    public function getUpdates(int $offset = 0, int $timeout = 20): array
    {
        $payload = [
            'timeout' => max(0, $timeout),
            'allowed_updates' => json_encode(['message', 'callback_query']),
        ];

        if ($offset > 0) {
            $payload['offset'] = $offset;
        }

        $data = $this->request('getUpdates', $payload);

        return is_array($data['result'] ?? null) ? $data['result'] : [];
    }

    public function deleteWebhook(): void
    {
        $this->request('deleteWebhook', ['drop_pending_updates' => false]);
    }

    public function setCommands(array $commands): void
    {
        $this->assertReady();
        $this->request('setMyCommands', [
            'commands' => json_encode($commands, JSON_UNESCAPED_UNICODE),
        ]);
    }

    public function testConnection(): void
    {
        $this->assertReady();

        $this->sendMessage('Limodelia CRM: Telegram jest połączony.');
    }

    private function assertReady(): void
    {
        if (!extension_loaded('curl')) {
            throw new RuntimeException('Rozszerzenie PHP cURL jest wymagane do komunikacji z Telegram.');
        }

        if (!$this->isConfigured()) {
            throw new RuntimeException('Telegram nie jest skonfigurowany w ustawieniach systemu.');
        }
    }

    private function request(string $method, array $payload): array
    {
        $this->assertReady();

        $token = (string) $this->settings['bot_token'];
        $url = 'https://api.telegram.org/bot' . $token . '/' . $method;
        $ch = curl_init($url);
        $timeout = isset($payload['timeout']) && is_numeric($payload['timeout'])
            ? max(20, (int) $payload['timeout'] + 5)
            : 20;

        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $timeout,
        ]);
        $raw = curl_exec($ch);
        $error = curl_error($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($raw === false || $error !== '') {
            throw new RuntimeException('Błąd połączenia z Telegram: ' . $error);
        }

        $data = json_decode((string) $raw, true);
        if (!is_array($data) || $code >= 400 || ($data['ok'] ?? false) !== true) {
            $message = is_array($data) ? (string) ($data['description'] ?? 'Nieznany błąd Telegram.') : 'Nieprawidłowa odpowiedź Telegram.';
            throw new RuntimeException($message);
        }

        return $data;
    }
}
