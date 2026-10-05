<?php

namespace App\Modules\Analytics\Application;

use App\Modules\Analytics\Domain\Models\EbookDailyMetric;
use App\Modules\Analytics\Domain\Models\SiteDailyMetric;
use App\Modules\Library\Domain\Models\Author;
use App\Modules\Library\Domain\Models\Category;
use App\Modules\Library\Domain\Models\Ebook;
use App\Modules\Library\Domain\Models\EbookDownloadStat;
use App\Modules\Library\Domain\Models\EbookFile;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class AnalyticsReport
{
    public const RANGES = [7, 30, 90, 365];

    public function normalizeRange(int $days): int
    {
        return in_array($days, self::RANGES, true) ? $days : 30;
    }

    /**
     * @return array<string, int|string|array<int, array<string, int|string>>>
     */
    public function report(int $days): array
    {
        $days = $this->normalizeRange($days);
        [$start, $end] = $this->period($days);

        return [
            'range' => $days,
            'start_date' => $start,
            'end_date' => $end,
            'summary' => $this->summary($days),
            'trend' => $this->trend($days),
            'top_books' => $this->topBooks($days),
            'top_categories' => $this->topCategories($days),
            'top_collections' => $this->topCollections($days),
        ];
    }

    /**
     * @return array<string, int>
     */
    public function summary(int $days): array
    {
        [$start, $end] = $this->period($this->normalizeRange($days));

        $totals = SiteDailyMetric::query()
            ->whereDate('metric_date', '>=', $start)
            ->whereDate('metric_date', '<=', $end)
            ->selectRaw('
                COALESCE(SUM(page_views), 0) AS page_views,
                COALESCE(SUM(home_views), 0) AS home_views,
                COALESCE(SUM(catalog_views), 0) AS catalog_views,
                COALESCE(SUM(directory_views), 0) AS directory_views,
                COALESCE(SUM(book_views), 0) AS book_views,
                COALESCE(SUM(reader_opens), 0) AS reader_opens,
                COALESCE(SUM(info_views), 0) AS info_views,
                COALESCE(SUM(downloads), 0) AS downloads
            ')
            ->first();

        return [
            'page_views' => (int) ($totals?->page_views ?? 0),
            'home_views' => (int) ($totals?->home_views ?? 0),
            'catalog_views' => (int) ($totals?->catalog_views ?? 0),
            'directory_views' => (int) ($totals?->directory_views ?? 0),
            'book_views' => (int) ($totals?->book_views ?? 0),
            'reader_opens' => (int) ($totals?->reader_opens ?? 0),
            'info_views' => (int) ($totals?->info_views ?? 0),
            'downloads' => (int) ($totals?->downloads ?? 0),
        ];
    }

    /**
     * @return array<int, array<string, int|string>>
     */
    public function trend(int $days): array
    {
        $days = $this->normalizeRange($days);
        [$start, $end] = $this->period($days);

        $rows = SiteDailyMetric::query()
            ->whereDate('metric_date', '>=', $start)
            ->whereDate('metric_date', '<=', $end)
            ->orderBy('metric_date')
            ->get()
            ->keyBy(fn (SiteDailyMetric $metric): string => $metric->metric_date->toDateString());

        $cursor = CarbonImmutable::parse($start);
        $finish = CarbonImmutable::parse($end);
        $result = [];

        while ($cursor->lte($finish)) {
            $date = $cursor->toDateString();
            /** @var SiteDailyMetric|null $row */
            $row = $rows->get($date);

            $result[] = [
                'date' => $date,
                'page_views' => (int) ($row?->page_views ?? 0),
                'book_views' => (int) ($row?->book_views ?? 0),
                'reader_opens' => (int) ($row?->reader_opens ?? 0),
                'downloads' => (int) ($row?->downloads ?? 0),
            ];

            $cursor = $cursor->addDay();
        }

        return $result;
    }

    /**
     * @return array<int, array<string, int|string>>
     */
    public function topBooks(int $days, int $limit = 10): array
    {
        [$start, $end] = $this->period($this->normalizeRange($days));

        return DB::table('ebook_daily_metrics as m')
            ->join('ebooks as e', 'e.id', '=', 'm.ebook_id')
            ->whereNull('e.deleted_at')
            ->whereDate('m.metric_date', '>=', $start)
            ->whereDate('m.metric_date', '<=', $end)
            ->groupBy('e.id', 'e.title', 'e.slug')
            ->select([
                'e.id',
                'e.title',
                'e.slug',
            ])
            ->selectRaw('
                SUM(m.detail_views) AS detail_views,
                SUM(m.reader_opens) AS reader_opens,
                SUM(m.downloads) AS downloads
            ')
            ->orderByRaw('
                (SUM(m.detail_views) + (SUM(m.reader_opens) * 3) + (SUM(m.downloads) * 4)) DESC
            ')
            ->orderByDesc('reader_opens')
            ->orderByDesc('downloads')
            ->limit(max(1, min(25, $limit)))
            ->get()
            ->map(fn ($row): array => [
                'id' => (int) $row->id,
                'title' => (string) $row->title,
                'slug' => (string) $row->slug,
                'detail_views' => (int) $row->detail_views,
                'reader_opens' => (int) $row->reader_opens,
                'downloads' => (int) $row->downloads,
            ])
            ->all();
    }

    /**
     * @return array<int, array<string, int|string>>
     */
    public function topCategories(int $days, int $limit = 8): array
    {
        [$start, $end] = $this->period($this->normalizeRange($days));

        return DB::table('ebook_daily_metrics as m')
            ->join('category_ebook as ce', 'ce.ebook_id', '=', 'm.ebook_id')
            ->join('categories as c', 'c.id', '=', 'ce.category_id')
            ->whereNull('c.deleted_at')
            ->whereDate('m.metric_date', '>=', $start)
            ->whereDate('m.metric_date', '<=', $end)
            ->groupBy('c.id', 'c.name', 'c.slug')
            ->select([
                'c.id',
                'c.name',
                'c.slug',
            ])
            ->selectRaw('
                SUM(m.detail_views) AS detail_views,
                SUM(m.reader_opens) AS reader_opens,
                SUM(m.downloads) AS downloads
            ')
            ->orderByRaw('
                (SUM(m.detail_views) + (SUM(m.reader_opens) * 3) + (SUM(m.downloads) * 4)) DESC
            ')
            ->limit(max(1, min(25, $limit)))
            ->get()
            ->map(fn ($row): array => [
                'id' => (int) $row->id,
                'name' => (string) $row->name,
                'slug' => (string) $row->slug,
                'detail_views' => (int) $row->detail_views,
                'reader_opens' => (int) $row->reader_opens,
                'downloads' => (int) $row->downloads,
            ])
            ->all();
    }

    /**
     * @return array<int, array<string, int|string>>
     */
    public function topCollections(int $days, int $limit = 8): array
    {
        [$start, $end] = $this->period($this->normalizeRange($days));

        return DB::table('ebook_daily_metrics as m')
            ->join('ebooks as e', 'e.id', '=', 'm.ebook_id')
            ->join('collections as c', 'c.id', '=', 'e.collection_id')
            ->whereNull('e.deleted_at')
            ->whereNull('c.deleted_at')
            ->whereDate('m.metric_date', '>=', $start)
            ->whereDate('m.metric_date', '<=', $end)
            ->groupBy('c.id', 'c.name', 'c.slug')
            ->select([
                'c.id',
                'c.name',
                'c.slug',
            ])
            ->selectRaw('
                SUM(m.detail_views) AS detail_views,
                SUM(m.reader_opens) AS reader_opens,
                SUM(m.downloads) AS downloads
            ')
            ->orderByRaw('
                (SUM(m.detail_views) + (SUM(m.reader_opens) * 3) + (SUM(m.downloads) * 4)) DESC
            ')
            ->limit(max(1, min(25, $limit)))
            ->get()
            ->map(fn ($row): array => [
                'id' => (int) $row->id,
                'name' => (string) $row->name,
                'slug' => (string) $row->slug,
                'detail_views' => (int) $row->detail_views,
                'reader_opens' => (int) $row->reader_opens,
                'downloads' => (int) $row->downloads,
            ])
            ->all();
    }

    /**
     * @return array<string, int|array<string, int>>
     */
    public function dashboard(): array
    {
        return [
            'total_ebooks' => Ebook::query()->count(),
            'public_ebooks' => Ebook::query()->publiclyVisible()->count(),
            'reader_opens' => (int) EbookDailyMetric::query()->sum('reader_opens'),
            'downloads' => (int) EbookDownloadStat::query()->sum('downloads'),
            'local_storage_bytes' => (int) EbookFile::query()
                ->where('source_type', 'local')
                ->sum('size_bytes'),
            'last_30_days' => $this->summary(30),
        ];
    }

    /**
     * @return array<int, array{key: string, label: string, value: int}>
     */
    public function publicStatistics(): array
    {
        $publicScope = fn (Builder $query): Builder => $query->publiclyVisible();

        return [
            [
                'key' => 'ebooks',
                'label' => 'Ebook publik',
                'value' => Ebook::query()->publiclyVisible()->count(),
            ],
            [
                'key' => 'authors',
                'label' => 'Penulis',
                'value' => Author::query()
                    ->where('is_active', true)
                    ->whereHas('ebooks', $publicScope)
                    ->count(),
            ],
            [
                'key' => 'categories',
                'label' => 'Kategori',
                'value' => Category::query()
                    ->where('is_active', true)
                    ->whereHas('ebooks', $publicScope)
                    ->count(),
            ],
            [
                'key' => 'downloads',
                'label' => 'Download',
                'value' => (int) EbookDownloadStat::query()
                    ->whereHas('ebook', $publicScope)
                    ->sum('downloads'),
            ],
        ];
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function period(int $days): array
    {
        $end = CarbonImmutable::now()->startOfDay();
        $start = $end->subDays(max(0, $days - 1));

        return [$start->toDateString(), $end->toDateString()];
    }
}
