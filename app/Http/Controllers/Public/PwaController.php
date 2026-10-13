<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Modules\Settings\Application\SettingsManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;

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
                ?? 'Perpustakaan digital untuk membaca buku.'),
        );
        $themeColor = $this->color(
            (string) ($appearance['primary_color'] ?? '#2563EB'),
            '#2563EB',
        );
        $iconVersion = substr(hash('sha256', (string) ($general['logo_path'] ?? '').'|'.(string) ($general['favicon_path'] ?? '')), 0, 12);
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
                    'src' => '/pwa/site-icon/192.png?v='.$iconVersion,
                    'sizes' => '192x192',
                    'type' => 'image/png',
                    'purpose' => 'any',
                ],
                [
                    'src' => '/pwa/site-icon/512.png?v='.$iconVersion,
                    'sizes' => '512x512',
                    'type' => 'image/png',
                    'purpose' => 'any',
                ],
                [
                    'src' => '/pwa/site-icon/maskable-512.png?v='.$iconVersion,
                    'sizes' => '512x512',
                    'type' => 'image/png',
                    'purpose' => 'maskable',
                ],
            ],
            'shortcuts' => [
                [
                    'name' => 'Katalog Buku',
                    'short_name' => 'Katalog',
                    'url' => '/library',
                    'icons' => [[
                        'src' => '/pwa/site-icon/192.png?v='.$iconVersion,
                        'sizes' => '192x192',
                        'type' => 'image/png',
                    ]],
                ],
                [
                    'name' => 'Cari Buku',
                    'short_name' => 'Cari',
                    'url' => '/search',
                    'icons' => [[
                        'src' => '/pwa/site-icon/192.png?v='.$iconVersion,
                        'sizes' => '192x192',
                        'type' => 'image/png',
                    ]],
                ],
            ],
            'prefer_related_applications' => false,
        ], 200, [
            'Content-Type' => 'application/manifest+json; charset=UTF-8',
            'Cache-Control' => 'public, max-age=300, must-revalidate',
            'X-Robots-Tag' => 'noindex, nofollow, noarchive',
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    public function siteIcon(string $variant): Response
    {
        $variants = [
            '192' => [192, 0.08, 'icon-192.png'],
            '512' => [512, 0.08, 'icon-512.png'],
            'maskable-512' => [512, 0.20, 'icon-maskable-512.png'],
            'apple-180' => [180, 0.08, 'apple-touch-icon.png'],
        ];

        if (! isset($variants[$variant])) {
            abort(404);
        }

        [$size, $margin, $fallback] = $variants[$variant];
        $general = $this->settings->public()['general'] ?? [];
        $disk = Storage::disk('public');
        $path = (string) (($general['logo_path'] ?? '') ?: ($general['favicon_path'] ?? ''));
        $raw = null;

        if ($path !== '' && $disk->exists($path) && $disk->size($path) <= 8 * 1024 * 1024) {
            $raw = $disk->get($path);
        }

        $source = $raw ? @imagecreatefromstring($raw) : false;
        if ($source === false) {
            $contents = file_get_contents(public_path('pwa/'.$fallback));
        } else {
            $canvas = imagecreatetruecolor($size, $size);
            $background = imagecolorallocate($canvas, 255, 255, 255);
            imagefill($canvas, 0, 0, $background);

            $width = imagesx($source);
            $height = imagesy($source);
            $available = (int) round($size * (1 - 2 * $margin));
            $scale = min($available / max(1, $width), $available / max(1, $height));
            $targetWidth = max(1, (int) round($width * $scale));
            $targetHeight = max(1, (int) round($height * $scale));

            imagecopyresampled(
                $canvas,
                $source,
                (int) floor(($size - $targetWidth) / 2),
                (int) floor(($size - $targetHeight) / 2),
                0, 0,
                $targetWidth, $targetHeight,
                $width, $height,
            );

            ob_start();
            imagepng($canvas, null, 6);
            $contents = ob_get_clean();
            imagedestroy($source);
            imagedestroy($canvas);
        }

        return response($contents, 200, [
            'Content-Type' => 'image/png',
            'Cache-Control' => 'public, max-age=300, must-revalidate',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function color(string $value, string $fallback): string
    {
        return preg_match('/^#[0-9A-Fa-f]{6}$/', $value) === 1
            ? strtoupper($value)
            : $fallback;
    }
}
