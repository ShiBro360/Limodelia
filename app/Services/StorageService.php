<?php
declare(strict_types=1);

namespace App\Services;

final class StorageService
{
    public function __construct(private readonly SettingsService $settings)
    {
    }

    public function tripFolder(int $tripId): string
    {
        return $this->path('trips', (string) $tripId);
    }

    public function tripGalleryFolder(int $tripId): string
    {
        return $this->path('trips', (string) $tripId, 'gallery');
    }

    public function tripCoverFolder(int $tripId): string
    {
        return $this->path('trips', (string) $tripId, 'cover');
    }

    public function studentFolder(int $studentId): string
    {
        return $this->path('students', (string) $studentId);
    }

    public function studentAvatarFolder(int $studentId): string
    {
        return $this->path('students', (string) $studentId, 'avatar');
    }

    public function studentDocumentsFolder(int $studentId): string
    {
        return $this->path('students', (string) $studentId, 'documents');
    }

    public function marketingInstagramFolder(): string
    {
        return $this->path('marketing', 'instagram');
    }

    public function marketingOtherFolder(): string
    {
        return $this->path('marketing', 'other');
    }

    public function reportsFolder(): string
    {
        return $this->path('reports');
    }

    public function contractsFolder(): string
    {
        return $this->path('documents', 'contracts');
    }

    public function invoicesFolder(): string
    {
        return $this->path('documents', 'invoices');
    }

    public function exportsFolder(): string
    {
        return $this->path('documents', 'exports');
    }

    public function temporaryFolder(): string
    {
        return $this->path('temp');
    }

    public function backupFolder(): string
    {
        return $this->path('backups');
    }

    public function localStudentAvatarDirectory(): string
    {
        return BASE_PATH . '/uploads/students';
    }

    public function localStudentAvatarPath(string $fileName): string
    {
        return 'uploads/students/' . ltrim($fileName, '/');
    }

    private function path(string ...$segments): string
    {
        return implode('/', array_filter(array_merge([$this->rootFolder()], array_map([$this, 'cleanSegment'], $segments)), static fn (string $segment): bool => $segment !== ''));
    }

    private function rootFolder(): string
    {
        $settings = $this->settings->cloudinary();
        $root = trim((string) ($settings['root_folder'] ?? ''), "/ \t\n\r\0\x0B");

        return $this->cleanSegment($root);
    }

    private function cleanSegment(string $segment): string
    {
        $segment = trim($segment, "/ \t\n\r\0\x0B");
        $segment = preg_replace('/[^A-Za-z0-9._-]+/', '-', $segment) ?? '';

        return trim($segment, '-');
    }
}
