<?php

namespace App\Modules\Library\Application\Storage;

use App\Modules\Settings\Application\SettingsManager;

class UploadPolicy
{
    public function __construct(
        private readonly SettingsManager $settings,
    ) {}

    public function maxPdfBytes(): int
    {
        return max(
            10 * 1024 * 1024,
            min(
                300 * 1024 * 1024,
                (int) $this->settings->get('uploads', 'max_pdf_mb') * 1024 * 1024,
            ),
        );
    }

    public function configuredChunkBytes(): int
    {
        return max(
            2 * 1024 * 1024,
            min(
                50 * 1024 * 1024,
                (int) $this->settings->get('uploads', 'chunk_size_mb') * 1024 * 1024,
            ),
        );
    }

    public function effectiveChunkBytes(): int
    {
        $configured = $this->configuredChunkBytes();
        $runtime = $this->runtimeRequestLimitBytes();

        if ($runtime === null) {
            return $configured;
        }

        $safeRuntime = max(512 * 1024, $runtime - (512 * 1024));

        return min($configured, $safeRuntime);
    }

    public function checksumEnabled(): bool
    {
        return (bool) $this->settings->get('uploads', 'checksum_enabled');
    }

    public function sessionLifetimeMinutes(): int
    {
        return 24 * 60;
    }

    private function runtimeRequestLimitBytes(): ?int
    {
        $limits = array_filter([
            $this->parseIniBytes((string) ini_get('upload_max_filesize')),
            $this->parseIniBytes((string) ini_get('post_max_size')),
        ]);

        if ($limits === []) {
            return null;
        }

        return min($limits);
    }

    private function parseIniBytes(string $value): ?int
    {
        $value = trim($value);

        if ($value === '' || $value === '0' || $value === '-1') {
            return null;
        }

        if (! preg_match('/^([0-9]+(?:\.[0-9]+)?)\s*([KMGTP]?)B?$/i', $value, $matches)) {
            return null;
        }

        $number = (float) $matches[1];
        $unit = strtoupper($matches[2] ?? '');

        $multiplier = match ($unit) {
            'K' => 1024,
            'M' => 1024 ** 2,
            'G' => 1024 ** 3,
            'T' => 1024 ** 4,
            'P' => 1024 ** 5,
            default => 1,
        };

        return (int) floor($number * $multiplier);
    }
}
