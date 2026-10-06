<?php

namespace App\Http\Middleware;

use App\Modules\Installer\Application\InstallerState;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ValidateHost
{
    public function __construct(
        private readonly InstallerState $installer,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (
            ! $this->installer->isInstalled()
            || ! (bool) config('security.enforce_host', false)
        ) {
            return $next($request);
        }

        $host = strtolower(rtrim($request->getHost(), '.'));

        abort_unless(
            $this->isAllowed($host),
            Response::HTTP_BAD_REQUEST,
            'Host tidak diizinkan.',
        );

        return $next($request);
    }

    private function isAllowed(string $host): bool
    {
        if ($host === '') {
            return false;
        }

        foreach ($this->allowedHosts() as $allowed) {
            if ($host === $allowed) {
                return true;
            }

            if (
                str_starts_with($allowed, '*.')
                && str_ends_with($host, substr($allowed, 1))
                && $host !== substr($allowed, 2)
            ) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<int, string>
     */
    private function allowedHosts(): array
    {
        $hosts = config('security.allowed_hosts', []);
        $hosts = is_array($hosts) ? $hosts : [];

        $appHost = parse_url((string) config('app.url'), PHP_URL_HOST);

        if (is_string($appHost) && $appHost !== '') {
            $hosts[] = strtolower(rtrim($appHost, '.'));
        }

        if (app()->environment(['local', 'testing'])) {
            $hosts = [
                ...$hosts,
                'localhost',
                '127.0.0.1',
                '::1',
            ];
        }

        return array_values(array_unique(array_filter(array_map(
            static fn (mixed $value): string => strtolower(
                rtrim(trim((string) $value), '.'),
            ),
            $hosts,
        ))));
    }
}
