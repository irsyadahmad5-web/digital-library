<?php

namespace App\Modules\Library\Support;

class CategoryIconRegistry
{
    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return [
            'BookOpen' => 'Buku',
            'Scale' => 'Fiqih / Hukum',
            'Sparkles' => 'Doa & Dzikir',
            'MoonStar' => 'Ratib / Ibadah',
            'Landmark' => 'Sejarah / Sirah',
            'MessageSquareQuote' => 'Hadits / Kutipan',
            'ShieldCheck' => 'Aqidah / Tauhid',
            'Heart' => 'Akhlak / Tasawuf',
            'BookMarked' => 'Al-Qur’an / Tafsir',
            'GraduationCap' => 'Pendidikan',
            'Languages' => 'Bahasa',
            'Brain' => 'Pengembangan Diri',
            'Library' => 'Referensi / Umum',
            'Briefcase' => 'Bisnis / Karier',
            'Palette' => 'Seni / Kreatif',
            'Music' => 'Musik',
            'Baby' => 'Anak',
            'Leaf' => 'Alam / Lingkungan',
            'Trophy' => 'Olahraga / Prestasi',
            'FlaskConical' => 'Sains',
            'Calculator' => 'Matematika',
            'Compass' => 'Perjalanan / Eksplorasi',
            'History' => 'Sejarah Umum',
            'Feather' => 'Sastra',
            'Star' => 'Pilihan',
            'Globe2' => 'Dunia / Sosial',
            'Users' => 'Komunitas',
            'Lightbulb' => 'Ide / Pengetahuan',
        ];
    }

    /**
     * @return list<string>
     */
    public static function names(): array
    {
        return array_keys(self::options());
    }

    public static function default(): string
    {
        return 'BookOpen';
    }
}
