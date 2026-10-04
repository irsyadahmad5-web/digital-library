<?php

namespace App\Http\Controllers\Admin\Ebooks\Storage;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Ebooks\Storage\ChunkUploadRequest;
use App\Http\Requests\Admin\Ebooks\Storage\StartUploadRequest;
use App\Modules\Audit\Application\AuditLogger;
use App\Modules\Library\Application\Storage\ChunkUploadManager;
use App\Modules\Library\Application\Storage\EbookFileManager;
use App\Modules\Library\Domain\Models\Ebook;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class EbookUploadController extends Controller
{
    public function start(
        StartUploadRequest $request,
        int $id,
        ChunkUploadManager $uploads,
    ): JsonResponse {
        $ebook = Ebook::query()->findOrFail($id);

        try {
            $session = $uploads->start(
                $ebook,
                $request->user()?->getKey(),
                (string) $request->validated('file_name'),
                (int) $request->validated('size_bytes'),
                $request->validated('sha256'),
            );
        } catch (DomainException $exception) {
            throw ValidationException::withMessages([
                'file' => $exception->getMessage(),
            ]);
        }

        return response()->json([
            'session' => $uploads->serializeSession($session),
        ]);
    }

    public function status(
        Request $request,
        int $id,
        string $session,
        ChunkUploadManager $uploads,
    ): JsonResponse {
        $ebook = Ebook::query()->findOrFail($id);

        try {
            $uploadSession = $uploads->status(
                $ebook,
                $session,
                $request->user()?->getKey(),
            );
        } catch (DomainException $exception) {
            throw ValidationException::withMessages([
                'file' => $exception->getMessage(),
            ]);
        }

        return response()->json([
            'session' => $uploads->serializeSession($uploadSession),
        ]);
    }

    public function chunk(
        ChunkUploadRequest $request,
        int $id,
        string $session,
        int $index,
        ChunkUploadManager $uploads,
    ): JsonResponse {
        $ebook = Ebook::query()->findOrFail($id);
        $chunk = $request->file('chunk');

        if (! $chunk) {
            throw ValidationException::withMessages([
                'chunk' => 'Chunk upload tidak ditemukan.',
            ]);
        }

        try {
            $uploadSession = $uploads->storeChunk(
                $ebook,
                $session,
                $request->user()?->getKey(),
                $index,
                $chunk,
                $request->validated('chunk_sha256'),
            );
        } catch (DomainException $exception) {
            throw ValidationException::withMessages([
                'chunk' => $exception->getMessage(),
            ]);
        }

        return response()->json([
            'session' => $uploads->serializeSession($uploadSession),
        ]);
    }

    public function complete(
        Request $request,
        int $id,
        string $session,
        ChunkUploadManager $uploads,
        EbookFileManager $files,
        AuditLogger $audit,
    ): JsonResponse {
        $ebook = Ebook::query()->findOrFail($id);

        try {
            $file = $uploads->complete(
                $ebook,
                $session,
                $request->user()?->getKey(),
            );
        } catch (DomainException $exception) {
            throw ValidationException::withMessages([
                'file' => $exception->getMessage(),
            ]);
        }

        $audit->log(
            'library.ebook.file.uploaded',
            actor: $request->user(),
            subjectType: 'ebook',
            subjectId: $ebook->getKey(),
            metadata: [
                'source_type' => 'local',
                'size_bytes' => $file->size_bytes,
                'sha256' => $file->sha256,
            ],
            request: $request,
        );

        return response()->json([
            'source' => $files->serialize($file),
        ]);
    }

    public function destroy(
        Request $request,
        int $id,
        string $session,
        ChunkUploadManager $uploads,
    ): JsonResponse {
        $ebook = Ebook::query()->findOrFail($id);

        try {
            $uploads->cancel(
                $ebook,
                $session,
                $request->user()?->getKey(),
            );
        } catch (DomainException $exception) {
            throw ValidationException::withMessages([
                'file' => $exception->getMessage(),
            ]);
        }

        return response()->json(['cancelled' => true]);
    }
}
