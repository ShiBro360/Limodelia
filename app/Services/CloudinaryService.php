<?php
declare(strict_types=1);

namespace App\Services;

use RuntimeException;

final class CloudinaryService
{
    private array $settings;

    public function __construct(private readonly SettingsService $settingsService)
    {
        $this->settings = $this->settingsService->cloudinary();
    }

    public function isConfigured(): bool
    {
        return (bool) ($this->settings['enabled'] ?? false)
            && !empty($this->settings['cloud_name'])
            && !empty($this->settings['api_key'])
            && !empty($this->settings['api_secret']);
    }

    public function upload(array $file, string $folder): array
    {
        $this->assertReady();

        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new RuntimeException('Nie udało się odczytać przesłanego zdjęcia.');
        }

        $path = (string) $file['tmp_name'];
        if (!is_uploaded_file($path)) {
            throw new RuntimeException('Nieprawidłowy plik zdjęcia.');
        }

        $timestamp = time();
        $params = [
            'folder' => $folder,
            'timestamp' => $timestamp,
        ];

        $post = [
            'file' => new \CURLFile($path, (string) ($file['type'] ?? 'image/jpeg'), (string) ($file['name'] ?? 'trip-photo')),
            'api_key' => $this->settings['api_key'],
            'folder' => $folder,
            'timestamp' => $timestamp,
            'signature' => $this->signature($params),
        ];

        $response = $this->request('image/upload', $post);

        return [
            'public_id' => (string) $response['public_id'],
            'secure_url' => (string) $response['secure_url'],
            'thumbnail_url' => $this->thumbnailUrl((string) $response['secure_url']),
            'original_url' => (string) $response['secure_url'],
            'version' => (string) ($response['version'] ?? ''),
            'file_name' => (string) ($file['name'] ?? ($response['original_filename'] ?? 'photo')),
            'file_size' => (int) ($response['bytes'] ?? 0),
            'width' => (int) ($response['width'] ?? 0),
            'height' => (int) ($response['height'] ?? 0),
            'format' => (string) ($response['format'] ?? ''),
            'folder' => $folder,
        ];
    }

    public function delete(string $publicId): void
    {
        $this->assertReady();

        $timestamp = time();
        $params = [
            'public_id' => $publicId,
            'timestamp' => $timestamp,
        ];

        $this->request('image/destroy', [
            'public_id' => $publicId,
            'timestamp' => $timestamp,
            'api_key' => $this->settings['api_key'],
            'signature' => $this->signature($params),
        ]);
    }

    public function testConnection(): array
    {
        $this->assertReady();

        $url = sprintf('https://api.cloudinary.com/v1_1/%s/usage', rawurlencode($this->settings['cloud_name']));
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_USERPWD => $this->settings['api_key'] . ':' . $this->settings['api_secret'],
        ]);
        $raw = curl_exec($ch);
        $error = curl_error($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($raw === false || $error !== '') {
            throw new RuntimeException('Błąd połączenia z Cloudinary: ' . $error);
        }

        $data = json_decode((string) $raw, true);
        if (!is_array($data) || $code >= 400) {
            $message = is_array($data) ? (string) ($data['error']['message'] ?? 'Nieznany błąd Cloudinary.') : 'Nieprawidłowa odpowiedź Cloudinary.';
            throw new RuntimeException($message);
        }

        return $data;
    }

    private function assertReady(): void
    {
        if (!extension_loaded('curl')) {
            throw new RuntimeException('Rozszerzenie PHP cURL jest wymagane do komunikacji z Cloudinary.');
        }

        if (!$this->isConfigured()) {
            throw new RuntimeException('Cloudinary nie jest skonfigurowane w ustawieniach systemu.');
        }
    }

    private function request(string $endpoint, array $post): array
    {
        $url = sprintf('https://api.cloudinary.com/v1_1/%s/%s', rawurlencode($this->settings['cloud_name']), $endpoint);
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $post,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 45,
        ]);
        $raw = curl_exec($ch);
        $error = curl_error($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($raw === false || $error !== '') {
            throw new RuntimeException('Błąd połączenia z Cloudinary: ' . $error);
        }

        $data = json_decode((string) $raw, true);
        if (!is_array($data) || $code >= 400) {
            $message = is_array($data) ? (string) ($data['error']['message'] ?? 'Nieznany błąd Cloudinary.') : 'Nieprawidłowa odpowiedź Cloudinary.';
            throw new RuntimeException($message);
        }

        return $data;
    }

    private function signature(array $params): string
    {
        ksort($params);
        $base = http_build_query($params, '', '&', PHP_QUERY_RFC3986);
        $base = str_replace('%2F', '/', $base);

        return sha1($base . $this->settings['api_secret']);
    }

    private function thumbnailUrl(string $secureUrl): string
    {
        return str_replace('/upload/', '/upload/c_fill,w_320,h_220,q_auto,f_auto/', $secureUrl);
    }
}
