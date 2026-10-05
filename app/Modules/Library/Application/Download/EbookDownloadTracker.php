<?php

namespace App\Modules\Library\Application\Download;

use App\Modules\Analytics\Application\AnalyticsTracker;
use App\Modules\Library\Domain\Models\Ebook;
use App\Modules\Library\Domain\Models\EbookDownloadStat;
use App\Modules\Settings\Application\SettingsManager;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class EbookDownloadTracker
{
    public function __construct(
        private readonly SettingsManager $settings,
        private readonly AnalyticsTracker $analytics,
    ) {}

    public function recordSuccessfulDownload(
        Ebook $ebook,
        Request $request,
        Response $response,
    ): void {
        if (! (bool) $this->settings->get('downloads', 'track_downloads')) {
            return;
        }

        if (! $request->isMethod('GET')) {
            return;
        }

        if ($response->getStatusCode() < 200 || $response->getStatusCode() >= 300) {
            return;
        }

        $range = trim((string) $request->header('Range'));

        if (
            $range !== ''
            && ! preg_match('/^bytes=0-(?:\d*)$/i', $range)
        ) {
            return;
        }

        $now = now();
        $updated = EbookDownloadStat::query()
            ->where('ebook_id', $ebook->getKey())
            ->update([
                'downloads' => DB::raw('downloads + 1'),
                'last_downloaded_at' => $now,
                'updated_at' => $now,
            ]);

        if ($updated > 0) {
            $this->analytics->recordDownload($request, $ebook);

            return;
        }

        try {
            EbookDownloadStat::query()->create([
                'ebook_id' => $ebook->getKey(),
                'downloads' => 1,
                'last_downloaded_at' => $now,
            ]);
        } catch (QueryException) {
            EbookDownloadStat::query()
                ->where('ebook_id', $ebook->getKey())
                ->update([
                    'downloads' => DB::raw('downloads + 1'),
                    'last_downloaded_at' => $now,
                    'updated_at' => $now,
                ]);
        }

        $this->analytics->recordDownload($request, $ebook);
    }
}
