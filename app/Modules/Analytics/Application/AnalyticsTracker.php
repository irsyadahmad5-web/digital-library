<?php

namespace App\Modules\Analytics\Application;

use App\Modules\Library\Domain\Models\Ebook;
use App\Modules\Settings\Application\SettingsManager;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AnalyticsTracker
{
    private const PAGE_SURFACES = [
        'home' => 'home_views',
        'catalog' => 'catalog_views',
        'directory' => 'directory_views',
        'book' => 'book_views',
        'info' => 'info_views',
    ];

    public function __construct(
        private readonly SettingsManager $settings,
    ) {}

    public function recordPageView(
        Request $request,
        string $surface,
        ?Ebook $ebook = null,
    ): void {
        if (
            ! $this->trackingEnabled()
            || ! (bool) $this->settings->get('analytics', 'track_page_views')
            || ! $this->shouldTrackRequest($request)
        ) {
            return;
        }

        $column = self::PAGE_SURFACES[$surface] ?? null;

        if ($column === null) {
            return;
        }

        $this->incrementSite([
            'page_views' => 1,
            $column => 1,
        ]);

        if ($surface === 'book' && $ebook !== null) {
            $this->incrementEbook($ebook, ['detail_views' => 1]);
        }
    }

    public function recordReaderOpen(Request $request, Ebook $ebook): void
    {
        if (
            ! $this->trackingEnabled()
            || ! (bool) $this->settings->get('analytics', 'track_reader_opens')
            || ! $this->shouldTrackRequest($request)
        ) {
            return;
        }

        $this->incrementSite([
            'page_views' => 1,
            'reader_opens' => 1,
        ]);
        $this->incrementEbook($ebook, ['reader_opens' => 1]);
    }

    public function recordDownload(Request $request, Ebook $ebook): void
    {
        if (
            ! $this->trackingEnabled()
            || ! $this->shouldTrackRequest($request)
        ) {
            return;
        }

        $this->incrementSite(['downloads' => 1]);
        $this->incrementEbook($ebook, ['downloads' => 1]);
    }

    private function trackingEnabled(): bool
    {
        return (bool) $this->settings->get('analytics', 'enabled');
    }

    private function shouldTrackRequest(Request $request): bool
    {
        if (! $request->isMethod('GET')) {
            return false;
        }

        $purpose = strtolower(implode(' ', [
            (string) $request->header('Purpose'),
            (string) $request->header('Sec-Purpose'),
        ]));

        return ! str_contains($purpose, 'prefetch')
            && ! str_contains($purpose, 'prerender');
    }

    /**
     * @param  array<string, int>  $increments
     */
    private function incrementSite(array $increments): void
    {
        $date = now()->toDateString();
        $now = now();
        $update = ['updated_at' => $now];

        foreach ($increments as $column => $amount) {
            $update[$column] = DB::raw($column.' + '.max(0, $amount));
        }

        $updated = DB::table('site_daily_metrics')
            ->where('metric_date', $date)
            ->update($update);

        if ($updated > 0) {
            return;
        }

        $row = [
            'metric_date' => $date,
            'page_views' => 0,
            'home_views' => 0,
            'catalog_views' => 0,
            'directory_views' => 0,
            'book_views' => 0,
            'reader_opens' => 0,
            'info_views' => 0,
            'downloads' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ];

        foreach ($increments as $column => $amount) {
            $row[$column] = max(0, $amount);
        }

        try {
            DB::table('site_daily_metrics')->insert($row);
        } catch (QueryException) {
            DB::table('site_daily_metrics')
                ->where('metric_date', $date)
                ->update($update);
        }
    }

    /**
     * @param  array<string, int>  $increments
     */
    private function incrementEbook(Ebook $ebook, array $increments): void
    {
        $date = now()->toDateString();
        $now = now();
        $update = ['updated_at' => $now];

        foreach ($increments as $column => $amount) {
            $update[$column] = DB::raw($column.' + '.max(0, $amount));
        }

        $updated = DB::table('ebook_daily_metrics')
            ->where('metric_date', $date)
            ->where('ebook_id', $ebook->getKey())
            ->update($update);

        if ($updated > 0) {
            return;
        }

        $row = [
            'metric_date' => $date,
            'ebook_id' => $ebook->getKey(),
            'detail_views' => 0,
            'reader_opens' => 0,
            'downloads' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ];

        foreach ($increments as $column => $amount) {
            $row[$column] = max(0, $amount);
        }

        try {
            DB::table('ebook_daily_metrics')->insert($row);
        } catch (QueryException) {
            DB::table('ebook_daily_metrics')
                ->where('metric_date', $date)
                ->where('ebook_id', $ebook->getKey())
                ->update($update);
        }
    }
}
