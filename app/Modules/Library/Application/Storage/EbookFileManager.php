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

                $file->forceFill([
                    'external_url' => $result['external_url'],
                    'mime_type' => $result['mime_type'],
                    'size_bytes' => $result['size_bytes'],
                    'etag' => $result['etag'],
                    'last_modified' => $result['last_modified'],
                    'verification_status' => $result['verification_status'],
                    'verified_at' => $result['verified_at'],
                    'last_checked_at' => $result['last_checked_at'],
                    'last_error' => null,
                ])->save();

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

        DB::transaction(fn () => $file->delete());

        if ($localPath) {
            Storage::disk('local')->delete($localPath);
        }
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
            'verified_at' => $file->verified_at?->toIso8601String(),
            'last_checked_at' => $file->last_checked_at?->toIso8601String(),
            'last_error' => $file->last_error,
        ];
    }
}
