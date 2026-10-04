<?php

namespace App\Http\Controllers\Admin\Ebooks\Storage;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Ebooks\Storage\ExternalSourceRequest;
use App\Modules\Audit\Application\AuditLogger;
use App\Modules\Library\Application\Storage\EbookFileManager;
use App\Modules\Library\Domain\Models\Ebook;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class EbookStorageController extends Controller
{
    public function external(
        ExternalSourceRequest $request,
        int $id,
        EbookFileManager $files,
        AuditLogger $audit,
    ): JsonResponse {
        $ebook = Ebook::query()->findOrFail($id);

        try {
            $file = $files->attachExternal(
                $ebook,
                (string) $request->validated('external_url'),
                $request->user()?->getKey(),
            );
        } catch (DomainException $exception) {
            throw ValidationException::withMessages([
                'external_url' => $exception->getMessage(),
            ]);
        }

        $audit->log(
            'library.ebook.file.external_attached',
            actor: $request->user(),
            subjectType: 'ebook',
            subjectId: $ebook->getKey(),
            metadata: [
                'source_type' => 'external_url',
                'verification_status' => $file->verification_status,
                'size_bytes' => $file->size_bytes,
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
        EbookFileManager $files,
        AuditLogger $audit,
    ): JsonResponse {
        $ebook = Ebook::query()->findOrFail($id);
        $sourceType = $ebook->file()->value('source_type');

        $files->remove($ebook);

        $audit->log(
            'library.ebook.file.removed',
            actor: $request->user(),
            subjectType: 'ebook',
            subjectId: $ebook->getKey(),
            metadata: ['source_type' => $sourceType],
            request: $request,
        );

        return response()->json(['removed' => true]);
    }
}
