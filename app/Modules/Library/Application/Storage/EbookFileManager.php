<?php

namespace App\Modules\Library\Application\Storage;

use App\Modules\Library\Domain\Models\Ebook;
use App\Modules\Library\Domain\Models\EbookFile;
use App\Modules\Settings\Application\SettingsManager;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class EbookFileManager
{
    public function __construct(
        private readonly ExternalPdfVerifier $externalVerifier,
        private readonly SettingsManager $settings,
    ) {}

    public function attachExternal(Ebook $ebook, string $url, ?int $userId): EbookFile
    {
        $verified = $this->externalVerifier->verify($url);
        $existing = EbookFile::query()->where('ebook_id', $ebook->getKey())->first();
        $oldLocalPath = $existing?->source_type === 'local'
            ? $existing->path
            : null;
        $oldPreviewPath = $existing?->preview_path;

        $file = DB::transaction(function () use ($ebook, $verified, $userId, $existing): EbookFile {
            $file = $existing ?? new EbookFile([
                'ebook_id' => $ebook->getKey(),
                'created_by' => $userId,
            ]);

            $file->fill([
                'source_type' => 'external_url',
                'disk' => null,
                'path' => null,
                'external_url' => $verified['external_url'],
                'original_name' => null,
                'mime_type' => $verified['mime_type'],
                'size_bytes' => $verified['size_bytes'],
                'sha256' => null,
                'etag' => $verified['etag'],
                'last_modified' => $verified['last_modified'],
                'verification_status' => $verified['verification_status'],
                'processing_status' => 'pending',
                'page_count' => null,
                'pdf_metadata' => null,
                'preview_path' => null,
                'processing_started_at' => null,
                'processed_at' => null,
                'processing_error' => null,
                'verified_at' => $verified['verified_at'],
                'last_checked_at' => $verified['last_checked_at'],
                'last_error' => null,
                'updated_by' => $userId,
            ]);
            $file->save();

            return $file->fresh();
        });

        if ($oldLocalPath) {
            Storage::disk('local')->delete($oldLocalPath);
        }

        if ($oldPreviewPath) {
            Storage::disk('public')->delete($oldPreviewPath);
        }

        return $file;
    }

    /**
     * @param  array{
     *     path: string,
     *     original_name: string,
     *     mime_type: string,
     *     size_bytes: int,
     *     sha256: string
     * }  $metadata
     */
    public function attachLocal(Ebook $ebook, array $metadata, ?int $userId): EbookFile
    {
        $existing = EbookFile::query()->where('ebook_id', $ebook->getKey())->first();
        $oldLocalPath = $existing?->source_type === 'local'
            ? $existing->path
            : null;
        $oldPreviewPath = $existing?->preview_path;

        $file = DB::transaction(function () use ($ebook, $metadata, $userId, $existing): EbookFile {
            $file = $existing ?? new EbookFile([
                'ebook_id' => $ebook->getKey(),
                'created_by' => $userId,
            ]);

            $file->fill([
                'source_type' => 'local',
                'disk' => 'local',
                'path' => $metadata['path'],
                'external_url' => null,
                'original_name' => $metadata['original_name'],
                'mime_type' => $metadata['mime_type'],
                'size_bytes' => $metadata['size_bytes'],
                'sha256' => $metadata['sha256'],
                'etag' => null,
                'last_modified' => null,
                'verification_status' => 'verified',
                'processing_status' => 'pending',
                'page_count' => null,
                'pdf_metadata' => null,
                'preview_path' => null,
                'processing_started_at' => null,
                'processed_at' => null,
                'processing_error' => null,
                'verified_at' => now(),
                'last_checked_at' => now(),
                'last_error' => null,
                'updated_by' => $userId,
            ]);
            $file->save();

            return $file->fresh();
        });

        if ($oldLocalPath && $oldLocalPath !== $metadata['path']) {
            Storage::disk('local')->delete($oldLocalPath);
        }

        if ($oldPreviewPath) {
            Storage::disk('public')->delete($oldPreviewPath);
        }

        return $file;
    }

    /**
     * @return array{checked: int, verified: int, failed: int}
     */
    public function reverifyDueExternalSources(int $limit = 100): array
    {
        if (! (bool) $this->settings->get('storage', 'verify_external_urls')) {
            return ['checked' => 0, 'verified' => 0, 'failed' => 0];
        }

        $intervalHours = max(
            1,
            min(
                168,
                (int) $this->settings->get('storage', 'verify_interval_hours'),
            ),
        );

        $files = EbookFile::query()
            ->where('source_type', 'external_url')
            ->whereNotNull('external_url')
            ->where(function ($query) use ($intervalHours): void {
                $query
                    ->whereNull('last_checked_at')
                    ->orWhere('last_checked_at', '<=', now()->subHours($intervalHours));
            })
            ->orderByRaw('last_checked_at IS NOT NULL')
            ->orderBy('last_checked_at')
            ->limit(max(1, min(500, $limit)))
            ->get();

        $verified = 0;
        $failed = 0;

        foreach ($files as $file) {
            try {
                $result = $this->externalVerifier->verify((string) $file->external_url);

                $contentChanged = $file->external_url !== $result['external_url']
                    || $file->size_bytes !== $result['size_bytes']
                    || $file->etag !== $result['etag']
                    || $file->last_modified !== $result['last_modified'];

                $payload = [
                    'external_url' => $result['external_url'],
                    'mime_type' => $result['mime_type'],
                    'size_bytes' => $result['size_bytes'],
                    'etag' => $result['etag'],
                    'last_modified' => $result['last_modified'],
                    'verification_status' => $result['verification_status'],
                    'verified_at' => $result['verified_at'],
                    'last_checked_at' => $result['last_checked_at'],
                    'last_error' => null,
                ];

                if ($contentChanged) {
                    $payload['processing_status'] = 'pending';
                    $payload['processing_error'] = null;
                    $payload['processed_at'] = null;
                }

                $file->forceFill($payload)->save();

                $verified++;
            } catch (Throwable $exception) {
                $file->forceFill([
                    'verification_status' => 'failed',
                    'last_checked_at' => now(),
                    'last_error' => Str::limit($exception->getMessage(), 2000, ''),
                ])->save();

                $failed++;
            }
        }

        return [
            'checked' => $files->count(),
            'verified' => $verified,
            'failed' => $failed,
        ];
    }

    public function remove(Ebook $ebook): void
    {
        $file = EbookFile::query()->where('ebook_id', $ebook->getKey())->first();

        if (! $file) {
            return;
        }

        $localPath = $file->source_type === 'local' ? $file->path : null;
        $previewPath = $file->preview_path;

        DB::transaction(fn () => $file->delete());

        if ($localPath) {
            Storage::disk('local')->delete($localPath);
        }

        if ($previewPath) {
            Storage::disk('public')->delete($previewPath);
        }
    }

    public function previewUrl(?EbookFile $file): ?string
    {
        if (! $file?->preview_path) {
            return null;
        }

        return Storage::disk('public')->url($file->preview_path);
    }

    /**
     * @return array<string, mixed>
     */
    public function serialize(?EbookFile $file): ?array
    {
        if (! $file) {
            return null;
        }

        return [
            'id' => $file->getKey(),
            'source_type' => $file->source_type,
            'original_name' => $file->original_name,
            'external_url' => $file->external_url,
            'mime_type' => $file->mime_type,
            'size_bytes' => $file->size_bytes,
            'sha256' => $file->sha256,
            'verification_status' => $file->verification_status,
            'processing_status' => $file->processing_status,
            'page_count' => $file->page_count,
            'pdf_metadata' => $file->pdf_metadata,
            'preview_url' => $this->previewUrl($file),
            'processed_at' => $file->processed_at?->toIso8601String(),
            'processing_error' => $file->processing_error,
            'verified_at' => $file->verified_at?->toIso8601String(),
            'last_checked_at' => $file->last_checked_at?->toIso8601String(),
            'last_error' => $file->last_error,
        ];
    }
}
