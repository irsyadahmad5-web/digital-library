<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        $order = [
            'hero' => 10,
            'latest_books' => 20,
            'popular_books' => 30,
            'categories' => 40,
            'collections' => 50,
            'search' => 60,
            'recommendations' => 70,
            'statistics' => 80,
        ];

        foreach ($order as $type => $sortOrder) {
            $row = DB::table('homepage_sections')
                ->where('type', $type)
                ->first();

            if (! $row) {
                continue;
            }

            $config = json_decode((string) $row->config_json, true);
            $config = is_array($config) ? $config : [];

            if ($type === 'hero') {
                $config['autoplay'] = (bool) ($config['autoplay'] ?? true);
                $config['interval_seconds'] = (int) ($config['interval_seconds'] ?? 6);
                $config['show_arrows'] = (bool) ($config['show_arrows'] ?? true);
                $config['show_dots'] = (bool) ($config['show_dots'] ?? true);
                $config['overlay_strength'] = (int) ($config['overlay_strength'] ?? 42);
                $config['slides'] = is_array($config['slides'] ?? null)
                    ? $config['slides']
                    : [];
            }

            if ($type === 'latest_books') {
                if (in_array(Str::lower((string) ($config['title'] ?? '')), ['ebook terbaru', ''], true)) {
                    $config['title'] = 'Buku Terbaru';
                }
                $config['show_view_all'] = (bool) ($config['show_view_all'] ?? true);
            }

            if ($type === 'popular_books') {
                if (in_array(Str::lower((string) ($config['title'] ?? '')), ['ebook populer', ''], true)) {
                    $config['title'] = 'Buku Populer';
                }
                if (in_array(Str::lower((string) ($config['eyebrow'] ?? '')), ['paling banyak diakses', ''], true)) {
                    $config['eyebrow'] = 'Paling banyak dibaca';
                }
                $config['show_view_all'] = (bool) ($config['show_view_all'] ?? true);
            }

            if ($type === 'search') {
                if (in_array(Str::lower((string) ($config['title'] ?? '')), ['temukan ebook', ''], true)) {
                    $config['title'] = 'Temukan buku';
                }
            }

            $updates = [
                'sort_order' => $sortOrder,
                'config_json' => json_encode($config, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'updated_at' => now(),
            ];

            if ($type === 'hero') {
                $updates['title'] = 'Hero Slider';
                $updates['is_enabled'] = true;
            }

            if ($type === 'latest_books') {
                $updates['title'] = 'Buku Terbaru';
                $updates['is_enabled'] = true;
            }

            if ($type === 'popular_books') {
                $updates['title'] = 'Buku Populer';
                $updates['is_enabled'] = true;
            }

            if ($type === 'search') {
                $updates['is_enabled'] = false;
            }

            DB::table('homepage_sections')
                ->where('id', $row->id)
                ->update($updates);
        }

        $this->replaceSettingDefault(
            'general',
            'description',
            'Perpustakaan digital untuk membaca dan mengunduh ebook secara mudah.',
            'Perpustakaan digital untuk membaca dan mengunduh buku secara mudah.',
        );
        $this->replaceSettingDefault(
            'seo',
            'meta_description',
            'Perpustakaan digital untuk membaca dan mengunduh ebook.',
            'Perpustakaan digital untuk membaca dan mengunduh buku.',
        );
        $this->replaceSettingDefault(
            'seo',
            'og_description',
            'Baca ebook langsung dari browser dengan nyaman.',
            'Baca buku langsung dari browser dengan nyaman.',
        );
    }

    private function replaceSettingDefault(
        string $group,
        string $key,
        string $old,
        string $new,
    ): void {
        $row = DB::table('settings')
            ->where('group', $group)
            ->where('key', $key)
            ->first();

        if (! $row) {
            return;
        }

        $value = json_decode((string) $row->value_json, true);

        if ($value !== $old) {
            return;
        }

        DB::table('settings')
            ->where('id', $row->id)
            ->update([
                'value_json' => json_encode($new, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        // Presentation migration: existing content/media is intentionally preserved.
    }
};
