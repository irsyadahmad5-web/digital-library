<?php

namespace App\Http\Controllers\Admin\Ebooks\Storage;

use App\Http\Controllers\Controller;
use App\Modules\Audit\Application\AuditLogger;
use App\Modules\Library\Application\Pdf\PdfProcessingManager;
use App\Modules\Library\Application\Storage\EbookFileManager;
use App\Modules\Library\Domain\Models\Ebook;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Throwable;

class EbookPdfProcessingController extends Controller
{
    public function store(
        Request $request,
        int $id,
        PdfProcessingManager $processor,
        EbookFileManager $files,
        AuditLogger $audit,
    ): JsonResponse {
        $ebook = Ebook::query()->with('file')->findOrFail($id);

        if (! $ebook->file) {
            throw ValidationException::withMessages([
                'file' => 'Ebook belum memiliki source PDF.',
            ]);
        }

        try {
            $file = $processor->process($ebook->file);
        } catch (DomainException $exception) {
            throw ValidationException::withMessages([
                'file' => $exception->getMessage(),
            ]);
        } catch (Throwable $exception) {
            report($exception);

            throw ValidationException::withMessages([
                'file' => 'PDF gagal diproses. Periksa processing error untuk detail.',
            ]);
        }

        $audit->log(
            'library.ebook.pdf.processed',
            actor: $request->user(),
            subjectType: 'ebook',
            subjectId: $ebook->getKey(),
            metadata: [
                'page_count' => $file->page_count,
                'source_type' => $file->source_type,
                'sha256' => $file->sha256,
            ],
            request: $request,
        );

        return response()->json([
            'source' => $files->serialize($file),
        ]);
    }
}
