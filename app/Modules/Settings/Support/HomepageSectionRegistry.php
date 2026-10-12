<?php

namespace App\Modules\Settings\Support;

class HomepageSectionRegistry
{
    /**
     * @return array<string, array<string, mixed>>
     */
    public function sections(): array
    {
        return [
            'hero' => [
                'label' => 'Hero Slider',
                'description' => 'Slider gambar utama responsif dengan gambar desktop dan mobile terpisah.',
                'provider_available' => true,
                'provider_note' => null,
                'default_enabled' => true,
                'default_title' => 'Hero Slider',
                'fields' => [
                    'autoplay' => $this->boolean('Putar otomatis', true),
                    'interval_seconds' => $this->number('Interval slide (detik)', 6, 4, 12),
                    'show_arrows' => $this->boolean('Tampilkan tombol navigasi', true),
                    'show_dots' => $this->boolean('Tampilkan indikator slide', true),
                    'overlay_strength' => $this->number('Kekuatan overlay (%)', 42, 0, 80),
                ],
            ],
            'latest_books' => [
                'label' => 'Buku Terbaru',
                'description' => 'Buku publik terbaru berdasarkan tanggal publish.',
                'provider_available' => true,
                'provider_note' => null,
                'default_enabled' => true,
                'default_title' => 'Buku Terbaru',
                'fields' => [
                    'eyebrow' => $this->text('Label kecil', 'Koleksi terbaru', 100),
                    'title' => $this->text('Judul section', 'Buku Terbaru', 120),
                    'limit' => $this->number('Jumlah buku', 8, 4, 24),
                    'show_view_all' => $this->boolean('Tampilkan link lihat semua', true),
                ],
            ],
            'popular_books' => [
                'label' => 'Buku Populer',
                'description' => 'Buku dengan jumlah pembukaan reader terbanyak berdasarkan analytics agregat.',
                'provider_available' => true,
                'provider_note' => 'Popularitas memakai jumlah pembukaan reader, bukan profil atau identitas pengunjung.',
                'default_enabled' => true,
                'default_title' => 'Buku Populer',
                'fields' => [
                    'eyebrow' => $this->text('Label kecil', 'Paling banyak dibaca', 100),
                    'title' => $this->text('Judul section', 'Buku Populer', 120),
                    'limit' => $this->number('Jumlah buku', 8, 4, 24),
                    'show_view_all' => $this->boolean('Tampilkan link lihat semua', true),
                ],
            ],
            'categories' => [
                'label' => 'Kategori',
                'description' => 'Kategori aktif yang mempunyai buku publik.',
                'provider_available' => true,
                'provider_note' => null,
                'default_enabled' => true,
                'default_title' => 'Kategori',
                'fields' => [
                    'eyebrow' => $this->text('Label kecil', 'Jelajahi topik', 100),
                    'title' => $this->text('Judul section', 'Kategori', 120),
                    'limit' => $this->number('Jumlah kategori', 12, 4, 24),
                    'show_view_all' => $this->boolean('Tampilkan link semua kategori', true),
                ],
            ],
            'collections' => [
                'label' => 'Koleksi',
                'description' => 'Koleksi aktif yang mempunyai buku publik.',
                'provider_available' => true,
                'provider_note' => null,
                'default_enabled' => true,
                'default_title' => 'Koleksi',
                'fields' => [
                    'eyebrow' => $this->text('Label kecil', 'Pilihan koleksi', 100),
                    'title' => $this->text('Judul section', 'Jelajahi koleksi', 120),
                    'limit' => $this->number('Jumlah koleksi', 8, 3, 16),
                    'show_view_all' => $this->boolean('Tampilkan link semua koleksi', true),
                ],
            ],
            'search' => [
                'label' => 'Pencarian',
                'description' => 'Kotak pencarian katalog pada homepage.',
                'provider_available' => true,
                'provider_note' => null,
                'default_enabled' => false,
                'default_title' => 'Cari Buku',
                'fields' => [
                    'title' => $this->text('Judul section', 'Temukan buku', 120),
                    'subtitle' => $this->textarea('Deskripsi', 'Cari berdasarkan judul, penulis, kategori, tag, penerbit, koleksi, bahasa, atau ISBN.', 300),
                    'placeholder' => $this->text('Placeholder pencarian', 'Cari judul, penulis, kategori, tag, penerbit, koleksi, atau ISBN...', 180),
                ],
            ],
            'recommendations' => [
                'label' => 'Rekomendasi',
                'description' => 'Rekomendasi homepage berbasis variasi kategori, koleksi, dan recency.',
                'provider_available' => true,
                'provider_note' => 'Discovery memilih buku lintas topik secara deterministik tanpa profiling pengunjung.',
                'default_enabled' => false,
                'default_title' => 'Rekomendasi',
                'fields' => [
                    'eyebrow' => $this->text('Label kecil', 'Untuk dijelajahi', 100),
                    'title' => $this->text('Judul section', 'Rekomendasi', 120),
                    'limit' => $this->number('Jumlah buku', 8, 4, 24),
                ],
            ],
            'statistics' => [
                'label' => 'Statistik',
                'description' => 'Ringkasan angka publik perpustakaan dari data agregat tanpa identitas pengunjung.',
                'provider_available' => true,
                'provider_note' => 'Menampilkan statistik agregat yang aman untuk publik.',
                'default_enabled' => false,
                'default_title' => 'Statistik',
                'fields' => [
                    'eyebrow' => $this->text('Label kecil', 'Perpustakaan dalam angka', 100),
                    'title' => $this->text('Judul section', 'Statistik', 120),
                ],
            ],
        ];
    }

    /** @return array<string, mixed> */
    public function section(string $type): array
    {
        return $this->sections()[$type] ?? [];
    }

    /** @return array<int, string> */
    public function types(): array
    {
        return array_keys($this->sections());
    }

    /** @return array<string, mixed> */
    public function defaults(string $type): array
    {
        $definition = $this->section($type);
        $defaults = [];

        foreach (($definition['fields'] ?? []) as $key => $field) {
            $defaults[$key] = $field['default'] ?? null;
        }

        if ($type === 'hero') {
            $defaults['slides'] = [];
        }

        return $defaults;
    }

    /** @return array<string, mixed> */
    private function text(string $label, string $default, int $max): array
    {
        return [
            'label' => $label,
            'type' => 'text',
            'default' => $default,
            'meta' => ['max' => $max],
        ];
    }

    /** @return array<string, mixed> */
    private function textarea(string $label, string $default, int $max): array
    {
        return [
            'label' => $label,
            'type' => 'textarea',
            'default' => $default,
            'meta' => ['max' => $max],
        ];
    }

    /** @return array<string, mixed> */
    private function boolean(string $label, bool $default): array
    {
        return [
            'label' => $label,
            'type' => 'boolean',
            'default' => $default,
            'meta' => [],
        ];
    }

    /** @return array<string, mixed> */
    private function number(string $label, int $default, int $min, int $max): array
    {
        return [
            'label' => $label,
            'type' => 'number',
            'default' => $default,
            'meta' => [
                'min' => $min,
                'max' => $max,
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function urlPath(string $label, string $default): array
    {
        return [
            'label' => $label,
            'type' => 'text',
            'default' => $default,
            'meta' => [
                'max' => 255,
                'hint' => 'Gunakan path internal seperti /library.',
            ],
        ];
    }
}
