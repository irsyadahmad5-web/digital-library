<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $nonce = Vite::useCspNonce();

        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set(
            'Referrer-Policy',
            'strict-origin-when-cross-origin',
        );
        $response->headers->set(
            'Permissions-Policy',
            'camera=(), microphone=(), geolocation=(), payment=(), usb=()',
        );
        $response->headers->set(
            'X-Permitted-Cross-Domain-Policies',
            'none',
        );
        $response->headers->remove('X-Powered-By');

        if ($this->isHtml($response)) {
            $response->headers->set('Cross-Origin-Opener-Policy', 'same-origin');
            $response->headers->set('Cross-Origin-Resource-Policy', 'same-origin');

            if ((bool) config('security.csp_enabled', true)) {
                $response->headers->set(
                    'Content-Security-Policy',
                    $this->contentSecurityPolicy($nonce, $request),
                );
            }
        }

        if (
            $request->isSecure()
            && (bool) config('security.hsts_enabled', false)
        ) {
            $hsts = 'max-age='.(int) config(
                'security.hsts_max_age',
                31536000,
            );

            if ((bool) config('security.hsts_include_subdomains', true)) {
                $hsts .= '; includeSubDomains';
            }

            if ((bool) config('security.hsts_preload', false)) {
                $hsts .= '; preload';
            }

            $response->headers->set(
                'Strict-Transport-Security',
                $hsts,
            );
        }

        return $response;
    }

    private function isHtml(Response $response): bool
    {
        $contentType = strtolower(
            (string) $response->headers->get('Content-Type'),
        );

        return str_starts_with($contentType, 'text/html')
            || str_starts_with($contentType, 'application/xhtml+xml');
    }

    private function contentSecurityPolicy(
        string $nonce,
        Request $request,
    ): string {
        $scriptSources = [
            "'self'",
            "'nonce-{$nonce}'",
            "'wasm-unsafe-eval'",
        ];
        $styleSources = ["'self'", "'unsafe-inline'"];
        $connectSources = ["'self'"];

        $hotOrigin = $this->viteHotOrigin();

        if ($hotOrigin !== null) {
            $scriptSources[] = $hotOrigin;
            $styleSources[] = $hotOrigin;
            $connectSources[] = $hotOrigin;

            $parts = parse_url($hotOrigin);

            if (is_array($parts) && isset($parts['host'])) {
                $wsScheme = ($parts['scheme'] ?? 'http') === 'https'
                    ? 'wss'
                    : 'ws';
                $wsOrigin = $wsScheme.'://'.$parts['host'];

                if (isset($parts['port'])) {
                    $wsOrigin .= ':'.$parts['port'];
                }

                $connectSources[] = $wsOrigin;
            }
        }

        $directives = [
            "default-src 'self'",
            "base-uri 'self'",
            "object-src 'none'",
            "frame-ancestors 'none'",
            "form-action 'self'",
            'script-src '.implode(' ', array_unique($scriptSources)),
            "script-src-attr 'none'",
            'style-src '.implode(' ', array_unique($styleSources)),
            "img-src 'self' data: blob: https:",
            "font-src 'self' data:",
            'connect-src '.implode(' ', array_unique($connectSources)),
            "media-src 'self' blob:",
            "worker-src 'self' blob:",
            "frame-src 'self' blob:",
            "manifest-src 'self'",
        ];

        if ($request->isSecure()) {
            $directives[] = 'upgrade-insecure-requests';
        }

        return implode('; ', $directives);
    }

    private function viteHotOrigin(): ?string
    {
        if (
            ! app()->environment(['local', 'testing'])
            || ! Vite::isRunningHot()
        ) {
            return null;
        }

        $hotFile = Vite::hotFile();

        if (! is_file($hotFile) || ! is_readable($hotFile)) {
            return null;
        }

        $url = trim((string) file_get_contents($hotFile));
        $parts = parse_url($url);

        if (
            ! is_array($parts)
            || ! isset($parts['scheme'], $parts['host'])
            || ! in_array($parts['scheme'], ['http', 'https'], true)
        ) {
            return null;
        }

        $origin = $parts['scheme'].'://'.$parts['host'];

        if (isset($parts['port'])) {
            $origin .= ':'.$parts['port'];
        }

        return $origin;
    }
}
