<?php

namespace App\Http\Middleware;

use App\Modules\Settings\Application\SettingsManager;
use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class PublicMaintenanceMode
{
    public function __construct(
        private readonly SettingsManager $settings,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->settings->get('maintenance', 'enabled')) {
            return $next($request);
        }

        $response = Inertia::render('Public/Maintenance', [
            'message' => $this->settings->get('maintenance', 'message'),
            'contactText' => $this->settings->get('maintenance', 'contact_text'),
        ])->toResponse($request);

        $response->setStatusCode(Response::HTTP_SERVICE_UNAVAILABLE);
        $response->headers->set('Retry-After', '300');
        $response->headers->set('Cache-Control', 'no-store, private');

        return $response;
    }
}
