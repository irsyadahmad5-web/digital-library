<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $hero = DB::table('homepage_sections')
            ->where('type', 'hero')
            ->first();

        if (! $hero) {
            return;
        }

        $config = json_decode((string) $hero->config_json, true);
        $config = is_array($config) ? $config : [];

        if (is_array($config['slides'] ?? null) && count($config['slides']) > 0) {
            return;
        }

        $config['slides'] = [
            [
                'key' => '4f9eb52c-d5b8-4e2a-a5fd-5d501c9ae111',
                'title' => 'Baca lebih nyaman, temukan lebih mudah.',
                'subtitle' => 'Jelajahi koleksi buku digital yang bisa dibaca langsung dari browser, kapan saja.',
                'alt' => '',
                'cta_label' => 'Buka katalog',
                'cta_href' => '/library',
                'desktop_path' => null,
                'mobile_path' => null,
            ],
            [
                'key' => '88acdf19-6a9c-451d-8f82-6f9cf5d4e222',
                'title' => 'Bacaan baik memperluas cara pandang.',
                'subtitle' => 'Temukan topik, penulis, dan koleksi yang membantu belajar lebih dalam dan terarah.',
                'alt' => '',
                'cta_label' => 'Jelajahi kategori',
                'cta_href' => '/categories',
                'desktop_path' => null,
                'mobile_path' => null,
            ],
            [
                'key' => 'd0f432a7-1c11-46d3-b1a6-8e6bc026e333',
                'title' => 'Satu halaman hari ini, wawasan baru esok hari.',
                'subtitle' => 'Bangun kebiasaan membaca dengan akses perpustakaan yang sederhana, cepat, dan terbuka.',
                'alt' => '',
                'cta_label' => 'Lihat buku terbaru',
                'cta_href' => '/library?sort=newest',
                'desktop_path' => null,
                'mobile_path' => null,
            ],
        ];

        DB::table('homepage_sections')
            ->where('id', $hero->id)
            ->update([
                'title' => 'Hero Slider',
                'is_enabled' => true,
                'config_json' => json_encode(
                    $config,
                    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
                ),
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        // Starter content is presentation data; keep administrator changes intact.
    }
};
