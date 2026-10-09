<?php

namespace Tests\Feature\Quality;

use Illuminate\Routing\Route as IlluminateRoute;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ReleaseRouteContractTest extends TestCase
{
    public function test_route_names_are_unique(): void
    {
        $names = collect(Route::getRoutes())
            ->map(
                fn (IlluminateRoute $route): ?string => $route->getName(),
            )
            ->filter()
            ->values();

        $this->assertSame(
            $names->count(),
            $names->unique()->count(),
            'Duplicate named routes were found.',
        );
    }

    public function test_protected_admin_routes_keep_authentication_and_account_guards(): void
    {
        $protected = collect(Route::getRoutes())
            ->filter(
                fn (IlluminateRoute $route): bool => str_starts_with($route->uri(), 'admin')
                    && in_array(
                        'auth',
                        $route->gatherMiddleware(),
                        true,
                    ),
            );

        $this->assertNotEmpty($protected);

        foreach ($protected as $route) {
            $middleware = $route->gatherMiddleware();

            $this->assertContains('admin.headers', $middleware, $route->uri());
            $this->assertContains('active', $middleware, $route->uri());
            $this->assertContains(
                'permission:admin.access',
                $middleware,
                $route->uri(),
            );
            $this->assertContains(
                'password.changed',
                $middleware,
                $route->uri(),
            );
        }
    }

    public function test_every_admin_route_is_either_guest_or_authenticated(): void
    {
        $adminRoutes = collect(Route::getRoutes())
            ->filter(
                fn (IlluminateRoute $route): bool => str_starts_with($route->uri(), 'admin'),
            );

        $this->assertNotEmpty($adminRoutes);

        foreach ($adminRoutes as $route) {
            $middleware = $route->gatherMiddleware();

            $this->assertTrue(
                in_array('guest', $middleware, true)
                    || in_array('auth', $middleware, true),
                "Admin route {$route->uri()} has no guest/auth boundary.",
            );
        }
    }

    public function test_installer_mutations_keep_open_guard_and_rate_limits(): void
    {
        foreach ([
            'install.database',
            'install.reconfigure',
            'install.complete',
        ] as $name) {
            $route = Route::getRoutes()->getByName($name);

            $this->assertInstanceOf(IlluminateRoute::class, $route);
            $this->assertSame(['POST'], $route->methods());

            $middleware = $route->gatherMiddleware();

            $this->assertContains('install.open', $middleware);
            $this->assertTrue(
                collect($middleware)->contains(
                    fn (string $item): bool => str_starts_with($item, 'throttle:'),
                ),
                "{$name} is missing a throttle.",
            );
        }
    }

    public function test_numeric_route_throttles_use_explicit_non_empty_prefixes(): void
    {
        foreach (Route::getRoutes() as $route) {
            foreach ($route->gatherMiddleware() as $middleware) {
                if (! str_starts_with($middleware, 'throttle:')) {
                    continue;
                }

                $arguments = explode(',', substr($middleware, strlen('throttle:')));

                if (! isset($arguments[0]) || ! ctype_digit((string) $arguments[0])) {
                    continue;
                }

                $this->assertGreaterThanOrEqual(
                    3,
                    count($arguments),
                    "{$route->uri()} numeric throttle is missing a route-specific prefix.",
                );
                $this->assertNotSame(
                    '',
                    trim((string) ($arguments[2] ?? '')),
                    "{$route->uri()} numeric throttle prefix must not be empty.",
                );
            }
        }
    }

    public function test_reader_and_download_routes_remain_read_only_and_maintenance_guarded(): void
    {
        foreach ([
            'reader.show',
            'reader.source',
            'books.download',
        ] as $name) {
            $route = Route::getRoutes()->getByName($name);

            $this->assertInstanceOf(IlluminateRoute::class, $route);
            $this->assertContains(
                'site.maintenance',
                $route->gatherMiddleware(),
            );

            $this->assertEmpty(
                array_intersect(
                    $route->methods(),
                    ['POST', 'PUT', 'PATCH', 'DELETE'],
                ),
                "{$name} unexpectedly accepts a mutating HTTP method.",
            );
        }
    }

    public function test_operational_and_pwa_endpoints_stay_outside_public_maintenance_gate(): void
    {
        foreach ([
            'health.ready',
            'pwa.manifest',
            'install.index',
        ] as $name) {
            $route = Route::getRoutes()->getByName($name);

            $this->assertInstanceOf(IlluminateRoute::class, $route);
            $this->assertNotContains(
                'site.maintenance',
                $route->gatherMiddleware(),
                "{$name} must remain available during public maintenance.",
            );
        }
    }

    public function test_private_local_disk_framework_serve_routes_are_disabled(): void
    {
        $this->assertFalse(
            (bool) config('filesystems.disks.local.serve'),
        );
        $this->assertNull(
            Route::getRoutes()->getByName('storage.local'),
        );
        $this->assertNull(
            Route::getRoutes()->getByName('storage.local.upload'),
        );
    }

    public function test_public_application_has_no_unintended_state_changing_routes(): void
    {
        $allowedPrefixes = ['admin', 'install'];

        $unexpected = collect(Route::getRoutes())
            ->filter(function (IlluminateRoute $route) use ($allowedPrefixes): bool {
                if (
                    array_intersect(
                        $route->methods(),
                        ['POST', 'PUT', 'PATCH', 'DELETE'],
                    ) === []
                ) {
                    return false;
                }

                return ! collect($allowedPrefixes)
                    ->contains(
                        fn (string $prefix): bool => $route->uri() === $prefix
                            || str_starts_with(
                                $route->uri(),
                                $prefix.'/',
                            ),
                    );
            })
            ->map(
                fn (IlluminateRoute $route): string => implode('|', $route->methods()).' '.$route->uri(),
            )
            ->values()
            ->all();

        $this->assertSame([], $unexpected);
    }
}
