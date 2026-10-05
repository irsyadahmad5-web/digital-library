<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Modules\Analytics\Application\AnalyticsReport;
use App\Modules\Settings\Application\SettingsManager;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AnalyticsController extends Controller
{
    public function __construct(
        private readonly AnalyticsReport $analytics,
        private readonly SettingsManager $settings,
    ) {}

    public function index(Request $request): Response
    {
        $range = $this->analytics->normalizeRange(
            (int) $request->integer('range', 30),
        );

        return Inertia::render('Admin/Analytics/Index', [
            'analytics' => $this->analytics->report($range),
            'tracking' => [
                'enabled' => (bool) $this->settings->get('analytics', 'enabled'),
                'track_page_views' => (bool) $this->settings->get('analytics', 'track_page_views'),
                'track_reader_opens' => (bool) $this->settings->get('analytics', 'track_reader_opens'),
                'track_downloads' => (bool) $this->settings->get('downloads', 'track_downloads'),
            ],
        ]);
    }
}
