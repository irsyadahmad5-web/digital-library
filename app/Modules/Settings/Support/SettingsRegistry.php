<?php

namespace App\Modules\Settings\Support;

class SettingsRegistry
{
    /**
     * @return array<string, array{
     *     label: string,
     *     description: string,
     *     fields: array<string, array<string, mixed>>
     * }>
     */
    public function groups(): array
    {
        return [
            'general' => [
                'label' => 'Identitas',
                'description' => 'Nama, kontak, logo, dan identitas utama perpustakaan.',
                'fields' => [
                    'site_name' => $this->makeField('Nama perpustakaan', 'text', 'Digital Library', true, ['required', 'string', 'max:120']),
                    'short_name' => $this->makeField('Nama singkat', 'text', 'Digital Library', true, ['required', 'string', 'max:60']),
                    'tagline' => $this->makeField('Tagline', 'text', 'Baca lebih nyaman, temukan lebih mudah.', true, ['nullable', 'string', 'max:180']),
                    'description' => $this->makeField('Deskripsi', 'textarea', 'Perpustakaan digital untuk membaca dan mengunduh ebook secara mudah.', true, ['nullable', 'string', 'max:1200']),
                    'organization_name' => $this->makeField('Nama organisasi', 'text', '', true, ['nullable', 'string', 'max:160']),
                    'address' => $this->makeField('Alamat', 'textarea', '', true, ['nullable', 'string', 'max:500']),
                    'phone' => $this->makeField('Telepon', 'text', '', true, ['nullable', 'string', 'max:40']),
                    'email' => $this->makeField('Email publik', 'email', '', true, ['nullable', 'email:rfc', 'max:255']),
                    'logo_path' => $this->imageField('Logo', 'logo', 'PNG, JPG, atau WebP. Maksimal 2 MB.'),
                    'favicon_path' => $this->imageField('Favicon', 'favicon', 'PNG atau ICO. Maksimal 1 MB.'),
                ],
            ],
            'appearance' => [
                'label' => 'Tampilan',
                'description' => 'Warna, tema, dan ukuran area konten publik.',
                'fields' => [
                    'primary_color' => $this->makeField('Warna utama', 'color', '#2563EB', true, ['required', 'regex:/^#[0-9A-Fa-f]{6}$/']),
                    'background_color' => $this->makeField('Warna latar', 'color', '#FAFAF7', true, ['required', 'regex:/^#[0-9A-Fa-f]{6}$/']),
                    'dark_mode_enabled' => $this->makeField('Izinkan dark mode', 'boolean', true, true, ['required', 'boolean']),
                    'theme_default' => $this->select('Tema default', 'system', true, ['system' => 'Ikuti perangkat', 'light' => 'Light', 'dark' => 'Dark']),
                    'content_max_width' => $this->number('Lebar maksimum konten (px)', 1280, true, 960, 1600),
                    'card_radius' => $this->select('Radius kartu', 'medium', true, ['small' => 'Kecil', 'medium' => 'Sedang', 'large' => 'Besar']),
                ],
            ],
            'homepage' => [
                'label' => 'Homepage',
                'description' => 'Konten hero dan section utama halaman depan.',
                'fields' => [
                    'hero_title' => $this->makeField('Judul hero', 'text', 'Baca lebih nyaman, temukan lebih mudah.', true, ['required', 'string', 'max:180']),
                    'hero_subtitle' => $this->makeField('Subjudul hero', 'textarea', 'Jelajahi koleksi ebook, baca langsung di browser, atau unduh PDF tanpa harus membuat akun.', true, ['nullable', 'string', 'max:500']),
                    'show_search' => $this->makeField('Tampilkan pencarian', 'boolean', true, true, ['required', 'boolean']),
                    'show_latest' => $this->makeField('Tampilkan buku terbaru', 'boolean', true, true, ['required', 'boolean']),
                    'latest_limit' => $this->number('Jumlah buku terbaru', 8, true, 4, 24),
                    'show_popular' => $this->makeField('Tampilkan buku populer', 'boolean', true, true, ['required', 'boolean']),
                    'popular_limit' => $this->number('Jumlah buku populer', 8, true, 4, 24),
                    'show_categories' => $this->makeField('Tampilkan kategori', 'boolean', true, true, ['required', 'boolean']),
                ],
            ],
            'reader' => [
                'label' => 'Reader',
                'description' => 'Pengalaman default pembaca PDF.',
                'fields' => [
                    'default_mode' => $this->select('Mode baca default', 'continuous', true, ['continuous' => 'Continuous scroll', 'single' => 'Single page', 'flip' => 'Flip seperti buku']),
                    'default_theme' => $this->select('Tema reader', 'light', true, ['light' => 'Light', 'sepia' => 'Sepia', 'dark' => 'Dark']),
                    'auto_hide_controls' => $this->makeField('Auto-hide controls', 'boolean', true, true, ['required', 'boolean']),
                    'hide_delay_ms' => $this->number('Waktu hide controls (ms)', 3500, true, 1000, 10000),
                    'default_zoom' => $this->number('Zoom default (%)', 100, true, 50, 200),
                    'page_gap_px' => $this->number('Jarak antar halaman (px)', 16, true, 0, 48),
                    'enable_flip_mode' => $this->makeField('Aktifkan mode flip', 'boolean', true, true, ['required', 'boolean']),
                ],
            ],
            'uploads' => [
                'label' => 'Upload',
                'description' => 'Batas dan strategi upload file ebook.',
                'fields' => [
                    'max_pdf_mb' => $this->number('Maksimum PDF (MB)', 300, false, 10, 300),
                    'chunk_size_mb' => $this->number('Ukuran chunk (MB)', 10, false, 2, 50),
                    'checksum_enabled' => $this->makeField('Verifikasi checksum', 'boolean', true, false, ['required', 'boolean']),
                    'max_cover_mb' => $this->number('Maksimum cover (MB)', 5, false, 1, 10),
                ],
            ],
            'downloads' => [
                'label' => 'Download',
                'description' => 'Kebijakan download ebook pada halaman publik.',
                'fields' => [
                    'public_enabled' => $this->makeField('Izinkan download publik', 'boolean', true, true, ['required', 'boolean']),
                    'track_downloads' => $this->makeField('Catat statistik download', 'boolean', true, false, ['required', 'boolean']),
                    'show_download_button' => $this->makeField('Tampilkan tombol download', 'boolean', true, true, ['required', 'boolean']),
                ],
            ],
            'analytics' => [
                'label' => 'Analytics',
                'description' => 'Statistik agregat tanpa menyimpan identitas, IP, user-agent, atau kata kunci pencarian pengunjung.',
                'fields' => [
                    'enabled' => $this->makeField('Aktifkan analytics agregat', 'boolean', true, false, ['required', 'boolean']),
                    'track_page_views' => $this->makeField('Catat kunjungan halaman', 'boolean', true, false, ['required', 'boolean']),
                    'track_reader_opens' => $this->makeField('Catat pembukaan reader', 'boolean', true, false, ['required', 'boolean']),
                ],
            ],
            'seo' => [
                'label' => 'SEO',
                'description' => 'Metadata pencarian dan social sharing.',
                'fields' => [
                    'title_suffix' => $this->makeField('Suffix judul', 'text', 'Digital Library', true, ['nullable', 'string', 'max:80']),
                    'meta_description' => $this->makeField('Meta description', 'textarea', 'Perpustakaan digital untuk membaca dan mengunduh ebook.', true, ['nullable', 'string', 'max:320']),
                    'og_title' => $this->makeField('Open Graph title', 'text', 'Digital Library', true, ['nullable', 'string', 'max:120']),
                    'og_description' => $this->makeField('Open Graph description', 'textarea', 'Baca ebook langsung dari browser dengan nyaman.', true, ['nullable', 'string', 'max:320']),
                    'robots_index' => $this->makeField('Izinkan indexing', 'boolean', true, true, ['required', 'boolean']),
                    'canonical_url' => $this->makeField('Canonical base URL', 'url', '', true, ['nullable', 'url:http,https', 'max:255']),
                    'google_site_verification' => $this->makeField('Google site verification', 'text', '', true, ['nullable', 'string', 'max:255']),
                ],
            ],
            'storage' => [
                'label' => 'Storage',
                'description' => 'Preferensi sumber ebook. Adapter lengkap dibangun pada stage storage.',
                'fields' => [
                    'preferred_source' => $this->select('Sumber default', 'local', false, ['local' => 'Upload lokal', 'external_url' => 'URL cloud/eksternal']),
                    'verify_external_urls' => $this->makeField('Verifikasi URL eksternal', 'boolean', true, false, ['required', 'boolean']),
                    'https_only_external' => $this->makeField('Hanya izinkan HTTPS', 'boolean', true, false, ['required', 'boolean']),
                    'external_timeout_seconds' => $this->number('Timeout verifikasi (detik)', 10, false, 3, 30),
                    'verify_interval_hours' => $this->number('Interval cek ulang URL (jam)', 24, false, 1, 168),
                ],
            ],
            'maintenance' => [
                'label' => 'Maintenance',
                'description' => 'Kontrol halaman maintenance publik tanpa mengunci admin.',
                'fields' => [
                    'enabled' => $this->makeField('Aktifkan maintenance publik', 'boolean', false, false, ['required', 'boolean']),
                    'message' => $this->makeField('Pesan maintenance', 'textarea', 'Perpustakaan sedang dalam pemeliharaan. Silakan kembali beberapa saat lagi.', true, ['required', 'string', 'max:500']),
                    'contact_text' => $this->makeField('Informasi kontak', 'text', '', true, ['nullable', 'string', 'max:180']),
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function group(string $group): array
    {
        return $this->groups()[$group] ?? [];
    }

    /**
     * @return array<string, mixed>
     */
    public function field(string $group, string $key): array
    {
        return $this->groups()[$group]['fields'][$key] ?? [];
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(string $group): array
    {
        $rules = [];

        foreach (($this->group($group)['fields'] ?? []) as $key => $field) {
            if (($field['type'] ?? null) === 'image') {
                continue;
            }

            $rules[$key] = $field['rules'];
        }

        if ($group === 'general') {
            $rules['logo'] = ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:2048'];
            $rules['favicon'] = ['nullable', 'file', 'mimes:png,ico', 'max:1024'];
            $rules['remove_logo'] = ['nullable', 'boolean'];
            $rules['remove_favicon'] = ['nullable', 'boolean'];
        }

        return $rules;
    }

    /**
     * @return array<string, mixed>
     */
    public function defaults(?string $group = null): array
    {
        if ($group !== null) {
            return collect($this->group($group)['fields'] ?? [])
                ->mapWithKeys(fn (array $field, string $key) => [$key => $field['default']])
                ->all();
        }

        return collect($this->groups())
            ->mapWithKeys(fn (array $definition, string $groupKey) => [
                $groupKey => collect($definition['fields'])
                    ->mapWithKeys(fn (array $field, string $key) => [$key => $field['default']])
                    ->all(),
            ])
            ->all();
    }

    /**
     * @return list<string>
     */
    public function groupNames(): array
    {
        return array_keys($this->groups());
    }

    /**
     * @param  array<string, string>  $options
     * @return array<string, mixed>
     */
    private function select(string $label, string $default, bool $public, array $options): array
    {
        return $this->makeField(
            $label,
            'select',
            $default,
            $public,
            ['required', 'string', 'in:'.implode(',', array_keys($options))],
            options: $options,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function number(string $label, int $default, bool $public, int $min, int $max): array
    {
        return $this->makeField(
            $label,
            'number',
            $default,
            $public,
            ['required', 'integer', "min:{$min}", "max:{$max}"],
            meta: ['min' => $min, 'max' => $max],
        );
    }

    /**
     * @param  array<int, mixed>  $rules
     * @param  array<string, string>  $options
     * @param  array<string, mixed>  $meta
     * @return array<string, mixed>
     */
    private function makeField(
        string $label,
        string $type,
        mixed $default,
        bool $public,
        array $rules,
        bool $encrypted = false,
        string $description = '',
        array $options = [],
        array $meta = [],
    ): array {
        return [
            'label' => $label,
            'type' => $type,
            'default' => $default,
            'public' => $public,
            'encrypted' => $encrypted,
            'description' => $description,
            'rules' => $rules,
            'options' => $options,
            'meta' => $meta,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function imageField(string $label, string $input, string $description): array
    {
        return [
            'label' => $label,
            'type' => 'image',
            'default' => '',
            'public' => true,
            'encrypted' => false,
            'description' => $description,
            'rules' => [],
            'options' => [],
            'meta' => ['input' => $input],
        ];
    }
}
