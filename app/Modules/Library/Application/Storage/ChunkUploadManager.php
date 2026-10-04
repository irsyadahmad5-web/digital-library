<?php

namespace App\Modules\Library\Application\Storage;

use App\Modules\Library\Domain\Models\Ebook;
use App\Modules\Library\Domain\Models\EbookFile;
use App\Modules\Library\Domain\Models\EbookUploadSession;
use DomainException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class ChunkUploadManager
{
    public function __construct(
        private readonly UploadPolicy $policy,
        private readonly EbookFileManager $files,
    ) {}

    public function start(
        Ebook $ebook,
        ?int $userId,
        string $originalName,
        int $totalSize,
        ?string $expectedSha256 = null,
    ): EbookUploadSession {
        $this->cleanupExpired($ebook, $userId);

        $originalName = $this->sanitizeFileName($originalName);

        if (! str_ends_with(strtolower($originalName), '.pdf')) {
            throw new DomainException('File yang dipilih harus berekstensi .pdf.');
        }

        if ($totalSize < 5 || $totalSize > $this->policy->maxPdfBytes()) {
            throw new DomainException('Ukuran PDF berada di luar batas yang diizinkan.');
        }

        $expectedSha256 = $expectedSha256
            ? strtolower(trim($expectedSha256))
            : null;

        $existing = EbookUploadSession::query()
            ->where('ebook_id', $ebook->getKey())
            ->where('user_id', $userId)
            ->where('original_name', $originalName)
            ->where('total_size', $totalSize)
            ->where('status', 'uploading')
            ->where('expires_at', '>', now())
            ->latest('created_at')
            ->first();

        if ($existing) {
            if (! $existing->expected_sha256 && $expectedSha256) {
                $existing->forceFill(['expected_sha256' => $expectedSha256])->save();
            }

            return $existing->refresh();
        }

        $id = (string) Str::uuid();
        $chunkSize = $this->policy->effectiveChunkBytes();

        return EbookUploadSession::query()->create([
            'id' => $id,
            'ebook_id' => $ebook->getKey(),
            'user_id' => $userId,
            'original_name' => $originalName,
            'total_size' => $totalSize,
            'chunk_size' => $chunkSize,
            'total_chunks' => (int) ceil($totalSize / $chunkSize),
            'received_chunks' => 0,
            'status' => 'uploading',
            'expected_sha256' => $expectedSha256,
            'temp_directory' => 'ebook-upload-tmp/'.$id,
            'expires_at' => now()->addMinutes($this->policy->sessionLifetimeMinutes()),
        ]);
    }

    public function status(
        Ebook $ebook,
        string $sessionId,
        ?int $userId,
    ): EbookUploadSession {
        $session = $this->ownedSession($ebook, $sessionId, $userId);

        if ($session->expires_at->isPast() && $session->status !== 'completed') {
            $this->cancel($ebook, $sessionId, $userId);

            throw new DomainException('Sesi upload sudah kedaluwarsa. Silakan mulai kembali.');
        }

        return $session;
    }

    public function storeChunk(
        Ebook $ebook,
        string $sessionId,
        ?int $userId,
        int $chunkIndex,
        UploadedFile $chunk,
        ?string $expectedChunkSha256 = null,
    ): EbookUploadSession {
        $session = $this->status($ebook, $sessionId, $userId);

        if ($session->status !== 'uploading') {
            throw new DomainException('Sesi upload tidak menerima chunk baru.');
        }

        if ($chunkIndex < 0 || $chunkIndex >= $session->total_chunks) {
            throw new DomainException('Nomor chunk berada di luar rentang sesi upload.');
        }

        $expectedSize = $this->expectedChunkSize($session, $chunkIndex);
        $actualSize = (int) $chunk->getSize();

        if ($actualSize !== $expectedSize) {
            throw new DomainException(
                "Ukuran chunk {$chunkIndex} tidak sesuai. Diharapkan {$expectedSize} byte.",
            );
        }

        $realPath = $chunk->getRealPath();

        if (! is_string($realPath) || $realPath === '') {
            throw new DomainException('Chunk sementara tidak dapat dibaca.');
        }

        $sha256 = hash_file('sha256', $realPath);

        if (! is_string($sha256)) {
            throw new DomainException('Checksum chunk gagal dihitung.');
        }

        if (
            $expectedChunkSha256
            && ! hash_equals(strtolower($expectedChunkSha256), strtolower($sha256))
        ) {
            throw new DomainException("Checksum chunk {$chunkIndex} tidak sesuai.");
        }

        $existing = $session->chunks()
            ->where('chunk_index', $chunkIndex)
            ->first();

        if (
            $existing
            && $existing->size_bytes === $actualSize
            && hash_equals($existing->sha256, $sha256)
        ) {
            return $this->refreshProgress($session);
        }

        $stored = $chunk->storeAs(
            $session->temp_directory,
            $chunkIndex.'.part',
            'local',
        );

        if (! is_string($stored) || $stored === '') {
            throw new DomainException('Chunk gagal disimpan ke temporary storage.');
        }

        $session->chunks()->updateOrCreate(
            ['chunk_index' => $chunkIndex],
            [
                'size_bytes' => $actualSize,
                'sha256' => $sha256,
            ],
        );

        $session->forceFill([
            'expires_at' => now()->addMinutes($this->policy->sessionLifetimeMinutes()),
            'last_error' => null,
        ])->save();

        return $this->refreshProgress($session);
    }

    public function complete(
        Ebook $ebook,
        string $sessionId,
        ?int $userId,
    ): EbookFile {
        $session = DB::transaction(function () use ($ebook, $sessionId, $userId): EbookUploadSession {
            $session = EbookUploadSession::query()
                ->where('ebook_id', $ebook->getKey())
                ->where('id', $sessionId)
                ->where('user_id', $userId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($session->status === 'completed') {
                return $session;
            }

            if ($session->status !== 'uploading') {
                throw new DomainException('Sesi upload tidak siap untuk diselesaikan.');
            }

            if ($session->expires_at->isPast()) {
                throw new DomainException('Sesi upload sudah kedaluwarsa.');
            }

            $chunks = $session->chunks()
                ->orderBy('chunk_index')
                ->get();

            if ($chunks->count() !== $session->total_chunks) {
                throw new DomainException('Belum semua chunk diterima server.');
            }

            foreach ($chunks as $index => $chunk) {
                if ($chunk->chunk_index !== $index) {
                    throw new DomainException('Urutan chunk upload belum lengkap.');
                }

                if ($chunk->size_bytes !== $this->expectedChunkSize($session, $index)) {
                    throw new DomainException('Ukuran salah satu chunk tidak konsisten.');
                }
            }

            $session->forceFill([
                'status' => 'assembling',
                'last_error' => null,
            ])->save();

            return $session->refresh();
        });

        if ($session->status === 'completed') {
            $file = EbookFile::query()->where('ebook_id', $ebook->getKey())->first();

            if (! $file) {
                throw new DomainException('Sesi selesai tetapi source ebook tidak ditemukan.');
            }

            return $file;
        }

        $disk = Storage::disk('local');
        $finalizingPath = 'ebook-upload-finalizing/'.$session->getKey().'.pdf';
        $newFinalPath = null;

        try {
            $disk->makeDirectory('ebook-upload-finalizing');
            $absoluteFinalizing = $disk->path($finalizingPath);
            $output = fopen($absoluteFinalizing, 'wb');

            if ($output === false) {
                throw new DomainException('File hasil assembly tidak dapat dibuat.');
            }

            $hash = hash_init('sha256');
            $written = 0;

            try {
                for ($index = 0; $index < $session->total_chunks; $index++) {
                    $chunkPath = $session->temp_directory.'/'.$index.'.part';

                    if (! $disk->exists($chunkPath)) {
                        throw new DomainException("Chunk {$index} tidak ditemukan pada storage.");
                    }

                    $input = fopen($disk->path($chunkPath), 'rb');

                    if ($input === false) {
                        throw new DomainException("Chunk {$index} tidak dapat dibaca.");
                    }

                    try {
                        while (! feof($input)) {
                            $buffer = fread($input, 1024 * 1024);

                            if ($buffer === false) {
                                throw new DomainException("Chunk {$index} gagal dibaca.");
                            }

                            if ($buffer === '') {
                                continue;
                            }

                            $length = strlen($buffer);

                            if (fwrite($output, $buffer) !== $length) {
                                throw new DomainException('Gagal menulis file PDF hasil assembly.');
                            }

                            hash_update($hash, $buffer);
                            $written += $length;
                        }
                    } finally {
                        fclose($input);
                    }
                }
            } finally {
                fclose($output);
            }

            if ($written !== $session->total_size) {
                throw new DomainException('Ukuran PDF hasil assembly tidak sesuai dengan file awal.');
            }

            $signatureHandle = fopen($absoluteFinalizing, 'rb');

            if ($signatureHandle === false) {
                throw new DomainException('PDF hasil assembly tidak dapat diverifikasi.');
            }

            try {
                $signature = fread($signatureHandle, 5);
            } finally {
                fclose($signatureHandle);
            }

            if ($signature !== '%PDF-') {
                throw new DomainException('File hasil upload bukan PDF yang valid.');
            }

            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $detectedMime = $finfo ? finfo_file($finfo, $absoluteFinalizing) : null;

            if ($finfo) {
                finfo_close($finfo);
            }

            if (
                is_string($detectedMime)
                && ! in_array($detectedMime, ['application/pdf', 'application/octet-stream'], true)
            ) {
                throw new DomainException('MIME file hasil upload tidak dikenali sebagai PDF.');
            }

            $sha256 = hash_final($hash);

            if (
                $this->policy->checksumEnabled()
                && $session->expected_sha256
                && ! hash_equals(strtolower($session->expected_sha256), strtolower($sha256))
            ) {
                throw new DomainException('Checksum SHA-256 file tidak sesuai.');
            }

            $targetDirectory = 'ebooks/'.$ebook->getKey();
            $newFinalPath = $targetDirectory.'/'.Str::uuid().'.pdf';

            $disk->makeDirectory($targetDirectory);

            if (! $disk->move($finalizingPath, $newFinalPath)) {
                throw new DomainException('PDF final gagal dipindahkan ke private storage.');
            }

            $file = $this->files->attachLocal(
                $ebook,
                [
                    'path' => $newFinalPath,
                    'original_name' => $session->original_name,
                    'mime_type' => 'application/pdf',
                    'size_bytes' => $written,
                    'sha256' => $sha256,
                ],
                $userId,
            );

            $session->forceFill([
                'status' => 'completed',
                'computed_sha256' => $sha256,
                'received_chunks' => $session->total_chunks,
                'completed_at' => now(),
                'last_error' => null,
            ])->save();

            $session->chunks()->delete();
            $disk->deleteDirectory($session->temp_directory);

            return $file;
        } catch (Throwable $exception) {
            $disk->delete($finalizingPath);

            if ($newFinalPath && ! EbookFile::query()
                ->where('ebook_id', $ebook->getKey())
                ->where('path', $newFinalPath)
                ->exists()) {
                $disk->delete($newFinalPath);
            }

            $session->forceFill([
                'status' => 'uploading',
                'last_error' => Str::limit($exception->getMessage(), 2000, ''),
            ])->save();

            throw $exception;
        }
    }

    public function cancel(
        Ebook $ebook,
        string $sessionId,
        ?int $userId,
    ): void {
        $session = $this->ownedSession($ebook, $sessionId, $userId);

        if ($session->status === 'completed') {
            return;
        }

        Storage::disk('local')->deleteDirectory($session->temp_directory);
        $session->delete();
    }

    /**
     * @return array<string, mixed>
     */
    public function serializeSession(EbookUploadSession $session): array
    {
        $session->loadMissing('chunks:id,upload_session_id,chunk_index,size_bytes,sha256');

        return [
            'id' => $session->getKey(),
            'original_name' => $session->original_name,
            'total_size' => $session->total_size,
            'chunk_size' => $session->chunk_size,
            'total_chunks' => $session->total_chunks,
            'received_chunks' => $session->received_chunks,
            'received_bytes' => (int) $session->chunks->sum('size_bytes'),
            'received_indices' => $session->chunks
                ->pluck('chunk_index')
                ->map(fn (mixed $value): int => (int) $value)
                ->values()
                ->all(),
            'received_chunks_meta' => $session->chunks
                ->map(fn ($chunk): array => [
                    'index' => (int) $chunk->chunk_index,
                    'size_bytes' => (int) $chunk->size_bytes,
                    'sha256' => $chunk->sha256,
                ])
                ->values()
                ->all(),
            'status' => $session->status,
            'expires_at' => $session->expires_at->toIso8601String(),
            'completed_at' => $session->completed_at?->toIso8601String(),
            'last_error' => $session->last_error,
        ];
    }

    private function ownedSession(
        Ebook $ebook,
        string $sessionId,
        ?int $userId,
    ): EbookUploadSession {
        return EbookUploadSession::query()
            ->where('ebook_id', $ebook->getKey())
            ->where('id', $sessionId)
            ->where('user_id', $userId)
            ->firstOrFail();
    }

    private function refreshProgress(EbookUploadSession $session): EbookUploadSession
    {
        $session->forceFill([
            'received_chunks' => $session->chunks()->count(),
        ])->save();

        return $session->refresh();
    }

    private function expectedChunkSize(
        EbookUploadSession $session,
        int $chunkIndex,
    ): int {
        $offset = $chunkIndex * $session->chunk_size;
        $remaining = $session->total_size - $offset;

        return min($session->chunk_size, $remaining);
    }

    public function cleanupAllExpired(): int
    {
        $expired = EbookUploadSession::query()
            ->whereIn('status', ['uploading', 'assembling'])
            ->where('expires_at', '<=', now())
            ->limit(500)
            ->get();

        foreach ($expired as $session) {
            Storage::disk('local')->deleteDirectory($session->temp_directory);
            $session->delete();
        }

        return $expired->count();
    }

    private function cleanupExpired(Ebook $ebook, ?int $userId): void
    {
        $expired = EbookUploadSession::query()
            ->where('ebook_id', $ebook->getKey())
            ->where('user_id', $userId)
            ->whereIn('status', ['uploading', 'assembling'])
            ->where('expires_at', '<=', now())
            ->limit(10)
            ->get();

        foreach ($expired as $session) {
            Storage::disk('local')->deleteDirectory($session->temp_directory);
            $session->delete();
        }
    }

    private function sanitizeFileName(string $name): string
    {
        $name = basename(str_replace('\\', '/', trim($name)));
        $name = preg_replace('/[\x00-\x1F\x7F]/u', '', $name) ?? '';
        $name = trim($name);

        if ($name === '') {
            return 'ebook.pdf';
        }

        return Str::limit($name, 240, '');
    }
}
