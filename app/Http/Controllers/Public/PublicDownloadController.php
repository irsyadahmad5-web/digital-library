<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Modules\Library\Application\Download\EbookDownloadTracker;
use App\Modules\Library\Application\PublicLibrary\PublicLibraryCatalog;
use App\Modules\Library\Application\Reader\PdfSourceStreamer;
use App\Modules\Settings\Application\SettingsManager;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PublicDownloadController extends Controller
{
    public function __construct(
        private readonly PublicLibraryCatalog $catalog,
        private readonly PdfSourceStreamer $streamer,
        private readonly SettingsManager $settings,
        private readonly EbookDownloadTracker $tracker,
    ) {}

    public function download(Request $request, string $slug): Response
    {
        $ebook = $this->catalog->findBook($slug);

        abort_unless(
            (bool) $this->settings->get('downloads', 'public_enabled')
                && $ebook->download_enabled,
            404,
        );

        $file = $ebook->file;

        abort_unless($file !== null, 404);

        $response = $this->streamer->download(
            $file,
            $request,
            $ebook->title.'.pdf',
        );

        $this->tracker->recordSuccessfulDownload(
            $ebook,
            $request,
            $response,
        );

        return $response;
    }
}
