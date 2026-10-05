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
                'label' => 'Hero',
                'description' => 'Judul utama, subjudul, dan kartu akses publik.',
                'provider_available' => true,
                'provider_note' => null,
                'default_enabled' => true,
                'default_title' => 'Hero',
                'fields' => [
                    'eyebrow' => $this->text('Label kecil', 'Perpustakaan digital', 100),
                    'title' => $this->text('Judul utama', 'Baca lebih nyaman, temukan lebih mudah.', 180),
                    'subtitle' => $this->textarea('Subjudul', 'Jelajahi koleksi ebook yang tersedia untuk publik.', 500),
                    'show_access_card' => $this->boolean('Tampilkan kartu akses publik', true),
                    'cta_label' => $this->text('Label tombol', 'Buka katalog', 80),
                    'cta_href' => $this->urlPath('Tujuan tombol', '/library'),
                ],
            ],
            'search' => [
                'label' => 'Search',
                'description' => 'Kotak pencarian katalog pada homepage.',
                'provider_available' => true,
                'provider_note' => null,
                'default_enabled' => true,
                'default_title' => 'Cari Ebook',
                'fields' => [
                    'title' => $this->text('Judul section', 'Temukan ebook', 120),
                    'subtitle' => $this->textarea('Deskripsi', 'Cari berdasarkan judul, penulis, kategori, tag, penerbit, koleksi, bahasa, atau ISBN.', 300),
                    'placeholder' => $this->text('Placeholder pencarian', 'Cari judul, penulis, kategori, tag, penerbit, koleksi, atau ISBN...', 180),
                ],
            ],
            'latest_books' => [
                'label' => 'Ebook Terbaru',
                'description' => 'Ebook publik terbaru berdasarkan tanggal publish.',
                'provider_available' => true,
                'provider_note' => null,
                'default_enabled' => true,
                'default_title' => 'Ebook Terbaru',
                'fields' => [
                    'eyebrow' => $this->text('Label kecil', 'Koleksi terbaru', 100),
                    'title' => $this->text('Judul section', 'Ebook terbaru', 120),
                    'limit' => $this->number('Jumlah ebook', 8, 4, 24),
                    'show_view_all' => $this->boolean('Tampilkan link lihat semua', true),
                ],
            ],
            'categories' => [
                'label' => 'Kategori',
                'description' => 'Kategori aktif yang mempunyai ebook publik.',
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
                'description' => 'Koleksi aktif yang mempunyai ebook publik.',
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
            'popular_books' => [
                'label' => 'Ebook Populer',
                'description' => 'Urutan berdasarkan statistik download agregat yang sudah tersedia.',
                'provider_available' => true,
                'provider_note' => 'Menggunakan counter download agregat Stage 16; analytics lanjutan tetap di Stage 18.',
                'default_enabled' => false,
                'default_title' => 'Ebook Populer',
                'fields' => [
                    'eyebrow' => $this->text('Label kecil', 'Paling banyak diakses', 100),
                    'title' => $this->text('Judul section', 'Ebook populer', 120),
                    'limit' => $this->number('Jumlah ebook', 8, 4, 24),
                ],
            ],
            'recommendations' => [
                'label' => 'Rekomendasi',
                'description' => 'Rekomendasi homepage berbasis variasi kategori, koleksi, dan recency.',
                'provider_available' => true,
                'provider_note' => 'Discovery Stage 17 memilih ebook lintas topik secara deterministik tanpa profiling pengunjung.',
                'default_enabled' => false,
                'default_title' => 'Rekomendasi',
                'fields' => [
                    'eyebrow' => $this->text('Label kecil', 'Untuk dijelajahi', 100),
                    'title' => $this->text('Judul section', 'Rekomendasi', 120),
                    'limit' => $this->number('Jumlah ebook', 8, 4, 24),
                ],
            ],
            'statistics' => [
                'label' => 'Statistik',
                'description' => 'Ringkasan angka perpustakaan berdasarkan data analytics.',
                'provider_available' => false,
                'provider_note' => 'Menunggu Stage 18 — Statistics & Analytics.',
                'default_enabled' => false,
                'default_title' => 'Statistik',
                'fields' => [
                    'eyebrow' => $this->text('Label kecil', 'Perpustakaan dalam angka', 100),
                    'title' => $this->text('Judul section', 'Statistik', 120),
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function section(string $type): array
    {
        return $this->sections()[$type] ?? [];
    }

    /**
     * @return array<int, string>
     */
    public function types(): array
    {
        return array_keys($this->sections());
    }

    /**
     * @return array<string, mixed>
     */
    public function defaults(string $type): array
    {
        $definition = $this->section($type);
        $defaults = [];

        foreach (($definition['fields'] ?? []) as $key => $field) {
            $defaults[$key] = $field['default'] ?? null;
        }

        return $defaults;
    }

    /**
     * @return array<string, mixed>
     */
    private function text(string $label, string $default, int $max): array
    {
        return [
            'label' => $label,
            'type' => 'text',
            'default' => $default,
            'meta' => ['max' => $max],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function textarea(string $label, string $default, int $max): array
    {
        return [
            'label' => $label,
            'type' => 'textarea',
            'default' => $default,
            'meta' => ['max' => $max],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function boolean(string $label, bool $default): array
    {
        return [
            'label' => $label,
            'type' => 'boolean',
            'default' => $default,
            'meta' => [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
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

    /**
     * @return array<string, mixed>
     */
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
