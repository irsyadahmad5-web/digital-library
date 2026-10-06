<?php

namespace App\Http\Controllers\Operations;

use App\Http\Controllers\Controller;
use App\Modules\Operations\Application\OperationsHealth;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class HealthController extends Controller
{
    public function __construct(
        private readonly OperationsHealth $health,
    ) {}

    public function ready(): JsonResponse
    {
        $report = $this->health->report();
        $status = $report['status'];

        return response()
            ->json([
                'status' => $status,
                'checked_at' => $report['checked_at'],
                'warnings' => $report['counts']['warning'],
                'critical' => $report['counts']['critical'],
            ], $status === 'unhealthy'
                ? Response::HTTP_SERVICE_UNAVAILABLE
                : Response::HTTP_OK)
            ->withHeaders([
                'Cache-Control' => 'no-store, private',
                'X-Robots-Tag' => 'noindex, nofollow, noarchive',
            ]);
    }
}
