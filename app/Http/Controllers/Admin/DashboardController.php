<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Modules\Analytics\Application\AnalyticsReport;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __construct(
        private readonly AnalyticsReport $analytics,
    ) {}

    public function index(): Response
    {
        return Inertia::render('Admin/Dashboard', [
            'summary' => $this->analytics->dashboard(),
        ]);
    }
}
