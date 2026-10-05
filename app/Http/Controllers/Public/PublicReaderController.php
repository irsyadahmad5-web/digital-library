<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Modules\Library\Application\PublicLibrary\PublicLibraryCatalog;
use App\Modules\Library\Application\Reader\PdfSourceStreamer;
use App\Modules\Library\Domain\Models\Ebook;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\Response;

class PublicReaderController extends Controller
{
    public function __construct(
        private readonly PublicLibraryCatalog $catalog,
        private readonly PdfSourceStreamer $streamer,
    ) {}

    public function show(string $slug): InertiaResponse
    {
        $ebook = $this->readableBook($slug);

        return Inertia::render('Reader/Index', [
            'book' => [
                'title' => $ebook->title,
                'subtitle' => $ebook->subtitle,
                'slug' => $ebook->slug,
                'authors' => $ebook->authors
                    ->where('is_active', true)
                    ->pluck('name')
                    ->values()
                    ->all(),
                'page_count' => $ebook->file?->page_count ?? $ebook->page_count,
            ],
            'sourceUrl' => route('reader.source', ['slug' => $ebook->slug]),
            'backUrl' => route('books.show', ['slug' => $ebook->slug]),
        ]);
    }

    public function source(Request $request, string $slug): Response
    {
        $ebook = $this->readableBook($slug);
        $file = $ebook->file;

        abort_unless($file !== null, 404);

        return $this->streamer->stream($file, $request);
    }

    private function readableBook(string $slug): Ebook
    {
        $ebook = $this->catalog->findBook($slug);

        abort_unless($ebook->read_enabled, 404);

        return $ebook;
    }
}
