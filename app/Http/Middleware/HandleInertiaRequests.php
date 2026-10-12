<?php

namespace App\Http\Middleware;

use App\Modules\Analytics\Application\AnalyticsReport;
use App\Modules\Library\Application\PublicLibrary\PublicLibraryCache;
use App\Modules\Settings\Application\SettingsManager;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();
        $site = app(SettingsManager::class)->public();

        return [
            ...parent::share($request),
            'appName' => $site['general']['site_name']
                ?? config('app.name', 'Digital Library'),
            'site' => $site,
            'publicStats' => fn (): array => $request->is('admin', 'admin/*')
                ? []
                : app(PublicLibraryCache::class)->remember(
                    'footer:statistics',
                    120,
                    fn (): array => app(AnalyticsReport::class)->footerStatistics(),
                ),
            'auth' => [
                'user' => $user ? [
                    'id' => $user->getKey(),
                    'name' => $user->name,
                    'email' => $user->email,
                    'roles' => fn () => $user->roles()->pluck('slug')->all(),
                    'permissions' => fn () => $user->permissions(),
                ] : null,
            ],
            'flash' => [
                'status' => fn () => $request->session()->get('status'),
            ],
        ];
    }
}
