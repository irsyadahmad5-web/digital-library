<?php

namespace Tests\Feature;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Tests\TestCase;

class UiAccessibilityContractTest extends TestCase
{
    public function test_semantic_palette_meets_small_text_contrast_targets(): void
    {
        $css = (string) file_get_contents(resource_path('css/app.css'));

        $canvas = $this->token($css, 'canvas');
        $surface = $this->token($css, 'surface');

        foreach (['ink', 'ink-soft', 'ink-faint', 'brand', 'success', 'warning', 'danger'] as $token) {
            $this->assertGreaterThanOrEqual(
                4.5,
                $this->contrast($this->token($css, $token), $canvas),
                $token.' must meet 4.5:1 against canvas.',
            );
            $this->assertGreaterThanOrEqual(
                4.5,
                $this->contrast($this->token($css, $token), $surface),
                $token.' must meet 4.5:1 against surface.',
            );
        }

        foreach ([
            ['brand', 'brand-soft'],
            ['success', 'success-soft'],
            ['warning', 'warning-soft'],
            ['danger', 'danger-soft'],
        ] as [$foreground, $background]) {
            $this->assertGreaterThanOrEqual(
                4.5,
                $this->contrast($this->token($css, $foreground), $this->token($css, $background)),
                $foreground.' must meet 4.5:1 against '.$background.'.',
            );
        }
    }

    public function test_global_accessibility_media_and_touch_contract_is_present(): void
    {
        $css = (string) file_get_contents(resource_path('css/app.css'));

        $this->assertStringContainsString('@media (prefers-reduced-motion: reduce)', $css);
        $this->assertStringContainsString('@media (prefers-contrast: more)', $css);
        $this->assertStringContainsString('@media (forced-colors: active)', $css);
        $this->assertStringContainsString('@media (pointer: coarse)', $css);
        $this->assertStringContainsString('min-width: 44px', $css);
        $this->assertStringContainsString('min-height: 44px', $css);
        $this->assertStringContainsString(':focus-visible', $css);
    }

    public function test_v2_vue_sources_do_not_reintroduce_legacy_visual_tokens(): void
    {
        $root = resource_path('js');
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root));
        $violations = [];
        $pattern = '/(?:rounded-2xl|border-border|bg-muted(?:\/\d+)?|text-muted-foreground|bg-background|text-foreground|bg-primary|text-primary)/';

        /** @var SplFileInfo $file */
        foreach ($iterator as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'vue') {
                continue;
            }

            $contents = (string) file_get_contents($file->getPathname());

            if (preg_match($pattern, $contents, $match) === 1) {
                $violations[] = str_replace($root.'/', '', $file->getPathname()).': '.$match[0];
            }
        }

        $this->assertSame([], $violations, "Legacy visual token usage found:\n".implode("\n", $violations));
    }

    public function test_offline_fallback_matches_v2_accessibility_baseline(): void
    {
        $offline = (string) file_get_contents(public_path('offline.html'));

        $this->assertStringContainsString('--canvas: #f7f6f2', $offline);
        $this->assertStringContainsString('--brand: #3157d5', $offline);
        $this->assertStringContainsString('min-height: 44px', $offline);
        $this->assertStringContainsString('@media (prefers-reduced-motion: reduce)', $offline);
        $this->assertStringContainsString('button:focus-visible', $offline);
        $this->assertStringContainsString('viewport-fit=cover', $offline);
    }

    private function token(string $css, string $token): string
    {
        $matched = preg_match(
            '/--'.preg_quote($token, '/').'\s*:\s*(#[0-9a-fA-F]{6})\s*;/',
            $css,
            $matches,
        );

        $this->assertSame(1, $matched, 'Missing CSS token --'.$token.'.');

        return strtoupper($matches[1]);
    }

    private function contrast(string $foreground, string $background): float
    {
        $foregroundLuminance = $this->luminance($foreground);
        $backgroundLuminance = $this->luminance($background);

        $lighter = max($foregroundLuminance, $backgroundLuminance);
        $darker = min($foregroundLuminance, $backgroundLuminance);

        return ($lighter + 0.05) / ($darker + 0.05);
    }

    private function luminance(string $hex): float
    {
        $hex = ltrim($hex, '#');
        $channels = [
            hexdec(substr($hex, 0, 2)) / 255,
            hexdec(substr($hex, 2, 2)) / 255,
            hexdec(substr($hex, 4, 2)) / 255,
        ];

        $channels = array_map(
            static fn (float $channel): float => $channel <= 0.04045
                ? $channel / 12.92
                : (($channel + 0.055) / 1.055) ** 2.4,
            $channels,
        );

        return (0.2126 * $channels[0])
            + (0.7152 * $channels[1])
            + (0.0722 * $channels[2]);
    }
}
