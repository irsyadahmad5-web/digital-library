<?php

namespace App\Modules\Library\Application\Pdf;

use App\Modules\Library\Application\Storage\UploadPolicy;
use App\Modules\Library\Domain\Models\EbookFile;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class PdfProcessingManager
{
    public function __construct(
        private readonly PdfToolchain $toolchain,
        private readonly ExternalPdfMaterializer $externalMaterializer,
        private readonly UploadPolicy $policy,
    ) {}

    public function process(EbookFile $file): EbookFile
    {
        $file = $this->beginProcessing($file);
        $localTemporaryPath = null;
        $newPreviewPath = null;

        try {
            if ($file->source_type === 'local') {
                $materialized = $this->localSource($file);
            } elseif ($file->source_type === 'external_url') {
                if (! $file->external_url) {
                    throw new DomainException('External source tidak memiliki URL.');
                }

                $materialized = $this->externalMaterializer->materialize(
                    (string) $file->external_url,
                );
                $localTemporaryPath = $materialized['path'];
            } else {
                throw new DomainException('Tipe source PDF tidak didukung.');
            }

            $absolutePdf = Storage::disk('local')->path($materialized['path']);
            $metadata = $this->toolchain->inspect($absolutePdf);
            $pageCount = (int) ($metadata['pages'] ?? 0);

            if ($pageCount < 1) {
                throw new DomainException('PDF tidak memiliki halaman yang dapat diproses.');
            }

            $preview = $this->renderPreview($file, $absolutePdf);
            $newPreviewPath = $preview['path'];
            $oldPreviewPath = $file->preview_path;

            $updated = DB::transaction(function () use (
                $file,
                $materialized,
                $metadata,
                $pageCount,
                $newPreviewPath,
            ): EbookFile {
                /** @var EbookFile $locked */
                $locked = EbookFile::query()
                    ->whereKey($file->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();

                $locked->forceFill([
                    'mime_type' => $materialized['mime_type'],
                    'size_bytes' => $materialized['size_bytes'],
                    'sha256' => $materialized['sha256'],
                    'processing_status' => 'processed',
                    'page_count' => $pageCount,
                    'pdf_metadata' => $metadata,
                    'preview_path' => $newPreviewPath,
                    'processed_at' => now(),
                    'processing_error' => null,
                ])->save();

                $locked->ebook()->update([
                    'page_count' => $pageCount,
                ]);

                return $locked->fresh();
            });

            if ($oldPreviewPath && $oldPreviewPath !== $newPreviewPath) {
                Storage::disk('public')->delete($oldPreviewPath);
            }

            return $updated;
        } catch (Throwable $exception) {
            if ($newPreviewPath) {
                Storage::disk('public')->delete($newPreviewPath);
            }

            EbookFile::query()
                ->whereKey($file->getKey())
                ->update([
                    'processing_status' => 'failed',
                    'processed_at' => null,
                    'processing_error' => Str::limit($exception->getMessage(), 2000, ''),
                ]);

            throw $exception;
        } finally {
            if ($localTemporaryPath) {
                Storage::disk('local')->delete($localTemporaryPath);
            }
        }
    }

    /**
     * @return array{checked: int, processed: int, failed: int}
     */
    public function processPending(int $limit = 5, ?int $ebookId = null): array
    {
        $query = EbookFile::query()
            ->where(function ($builder): void {
                $builder
                    ->where('processing_status', 'pending')
                    ->orWhere(function ($stale): void {
                        $stale
                            ->where('processing_status', 'processing')
                            ->where('processing_started_at', '<=', now()->subMinutes(30));
                    });
            });

        if ($ebookId !== null) {
            $query->where('ebook_id', $ebookId);
        }

        $files = $query
            ->orderByRaw("CASE WHEN processing_status = 'pending' THEN 0 ELSE 1 END")
            ->orderBy('updated_at')
            ->limit(max(1, min(50, $limit)))
            ->get();

        $processed = 0;
        $failed = 0;

        foreach ($files as $file) {
            try {
                $this->process($file);
                $processed++;
            } catch (Throwable) {
                $failed++;
            }
        }

        return [
            'checked' => $files->count(),
            'processed' => $processed,
            'failed' => $failed,
        ];
    }

    /**
     * @return array{path: string, size_bytes: int, sha256: string, mime_type: string}
     */
    private function localSource(EbookFile $file): array
    {
        if (! $file->path || $file->disk !== 'local') {
            throw new DomainException('Private local source tidak memiliki path yang valid.');
        }

        $disk = Storage::disk('local');

        if (! $disk->exists($file->path)) {
            throw new DomainException('File PDF private tidak ditemukan pada storage.');
        }

        $absolute = $disk->path($file->path);
        $size = (int) filesize($absolute);

        if ($size < 5 || $size > $this->policy->maxPdfBytes()) {
            throw new DomainException('Ukuran private PDF berada di luar batas yang diizinkan.');
        }

        if ($file->size_bytes !== null && (int) $file->size_bytes !== $size) {
            throw new DomainException('Ukuran private PDF berubah sejak upload dan integritasnya diragukan.');
        }

        $signature = $this->readSignature($absolute);

        if ($signature !== '%PDF-') {
            throw new DomainException('Private source bukan PDF yang valid.');
        }

        $sha256 = hash_file('sha256', $absolute);

        if (! is_string($sha256)) {
            throw new DomainException('Checksum private PDF gagal dihitung.');
        }

        if ($file->sha256 && ! hash_equals(strtolower($file->sha256), strtolower($sha256))) {
            throw new DomainException('Checksum private PDF berubah sejak upload.');
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = $finfo ? finfo_file($finfo, $absolute) : null;

        if ($finfo) {
            finfo_close($finfo);
        }

        if (
            is_string($mime)
            && ! in_array($mime, ['application/pdf', 'application/octet-stream'], true)
        ) {
            throw new DomainException('MIME private source tidak dikenali sebagai PDF.');
        }

        return [
            'path' => $file->path,
            'size_bytes' => $size,
            'sha256' => $sha256,
            'mime_type' => 'application/pdf',
        ];
    }

    /**
     * @return array{path: string}
     */
    private function renderPreview(EbookFile $file, string $absolutePdf): array
    {
        $privateDisk = Storage::disk('local');
        $publicDisk = Storage::disk('public');
        $tempDirectory = 'pdf-preview-temp/'.Str::uuid();
        $privateDisk->makeDirectory($tempDirectory);

        $outputPrefix = $privateDisk->path($tempDirectory.'/page-1');

        try {
            $rendered = $this->toolchain->renderFirstPage($absolutePdf, $outputPrefix);
            $stream = fopen($rendered, 'rb');

            if ($stream === false) {
                throw new DomainException('Thumbnail hasil render tidak dapat dibaca.');
            }

            $previewPath = 'ebooks/generated-previews/'.$file->ebook_id
                .'/preview-'.Str::uuid().'.jpg';

            try {
                if (! $publicDisk->put($previewPath, $stream)) {
                    throw new DomainException('Thumbnail gagal disimpan ke public storage.');
                }
            } finally {
                fclose($stream);
            }

            return ['path' => $previewPath];
        } finally {
            $privateDisk->deleteDirectory($tempDirectory);
        }
    }

    private function beginProcessing(EbookFile $file): EbookFile
    {
        return DB::transaction(function () use ($file): EbookFile {
            /** @var EbookFile $locked */
            $locked = EbookFile::query()
                ->whereKey($file->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if (
                $locked->processing_status === 'processing'
                && $locked->processing_started_at
                && $locked->processing_started_at->gt(now()->subMinutes(30))
            ) {
                throw new DomainException('PDF sedang diproses oleh proses lain.');
            }

            $locked->forceFill([
                'processing_status' => 'processing',
                'processing_started_at' => now(),
                'processed_at' => null,
                'processing_error' => null,
            ])->save();

            return $locked->fresh();
        });
    }

    private function readSignature(string $absolutePath): string
    {
        $handle = fopen($absolutePath, 'rb');

        if ($handle === false) {
            throw new DomainException('PDF tidak dapat dibaca untuk verifikasi signature.');
        }

        try {
            $signature = fread($handle, 5);

            return is_string($signature) ? $signature : '';
        } finally {
            fclose($handle);
        }
    }
}
