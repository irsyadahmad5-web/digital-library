<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Modules\Settings\Application\SettingsManager;
use Illuminate\Http\JsonResponse;

class PwaController extends Controller
{
    public function __construct(
        private readonly SettingsManager $settings,
    ) {}

    public function manifest(): JsonResponse
    {
        $site = $this->settings->public();
        $general = $site['general'] ?? [];
        $appearance = $site['appearance'] ?? [];

        $name = trim((string) ($general['site_name'] ?? 'Digital Library'));
        $shortName = trim((string) ($general['short_name'] ?? $name));
        $description = trim(
            (string) ($general['description']
                ?? 'Perpustakaan digital untuk membaca ebook.'),
        );
        $themeColor = $this->color(
            (string) ($appearance['primary_color'] ?? '#2563EB'),
            '#2563EB',
        );
        $backgroundColor = $this->color(
            (string) ($appearance['background_color'] ?? '#FAFAF7'),
            '#FAFAF7',
        );

        return response()->json([
            'id' => '/',
            'name' => $name !== '' ? $name : 'Digital Library',
            'short_name' => $shortName !== ''
                ? mb_substr($shortName, 0, 60)
                : 'Digital Library',
            'description' => $description,
            'lang' => 'id',
            'dir' => 'ltr',
            'start_url' => '/',
            'scope' => '/',
            'display' => 'standalone',
            'display_override' => [
                'window-controls-overlay',
                'standalone',
                'minimal-ui',
            ],
            'orientation' => 'any',
            'background_color' => $backgroundColor,
            'theme_color' => $themeColor,
            'categories' => [
                'books',
                'education',
                'productivity',
            ],
            'icons' => [
                [
                    'src' => '/pwa/icon-192.png',
                    'sizes' => '192x192',
                    'type' => 'image/png',
                    'purpose' => 'any',
                ],
                [
                    'src' => '/pwa/icon-512.png',
                    'sizes' => '512x512',
                    'type' => 'image/png',
                    'purpose' => 'any',
                ],
                [
                    'src' => '/pwa/icon-maskable-512.png',
                    'sizes' => '512x512',
                    'type' => 'image/png',
                    'purpose' => 'maskable',
                ],
            ],
            'shortcuts' => [
                [
                    'name' => 'Katalog Ebook',
                    'short_name' => 'Katalog',
                    'url' => '/library',
                    'icons' => [[
                        'src' => '/pwa/icon-192.png',
                        'sizes' => '192x192',
                        'type' => 'image/png',
                    ]],
                ],
                [
                    'name' => 'Cari Ebook',
                    'short_name' => 'Cari',
                    'url' => '/search',
                    'icons' => [[
                        'src' => '/pwa/icon-192.png',
                        'sizes' => '192x192',
                        'type' => 'image/png',
                    ]],
                ],
            ],
            'prefer_related_applications' => false,
        ], 200, [
            'Content-Type' => 'application/manifest+json; charset=UTF-8',
            'Cache-Control' => 'public, max-age=3600, must-revalidate',
            'X-Robots-Tag' => 'noindex, nofollow, noarchive',
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    private function color(string $value, string $fallback): string
    {
        return preg_match('/^#[0-9A-Fa-f]{6}$/', $value) === 1
            ? strtoupper($value)
            : $fallback;
    }
}
