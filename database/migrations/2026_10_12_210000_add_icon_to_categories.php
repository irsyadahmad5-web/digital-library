<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->string('icon', 64)
                ->default('BookOpen')
                ->after('description');
        });

        DB::table('categories')
            ->select(['id', 'name'])
            ->orderBy('id')
            ->chunkById(100, function ($categories): void {
                foreach ($categories as $category) {
                    DB::table('categories')
                        ->where('id', $category->id)
                        ->update([
                            'icon' => $this->iconFor((string) $category->name),
                        ]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropColumn('icon');
        });
    }

    private function iconFor(string $name): string
    {
        $needle = Str::lower(Str::ascii($name));

        return match (true) {
            str_contains($needle, 'fiqih'),
            str_contains($needle, 'fikih') => 'Scale',

            str_contains($needle, 'doa'),
            str_contains($needle, 'dzikir'),
            str_contains($needle, 'zikir'),
            str_contains($needle, 'wirid') => 'Sparkles',

            str_contains($needle, 'ratib'),
            str_contains($needle, 'rotib') => 'MoonStar',

            str_contains($needle, 'sirah'),
            str_contains($needle, 'tarikh') => 'Landmark',

            str_contains($needle, 'hadis'),
            str_contains($needle, 'hadits') => 'MessageSquareQuote',

            str_contains($needle, 'aqidah'),
            str_contains($needle, 'tauhid') => 'ShieldCheck',

            str_contains($needle, 'akhlak'),
            str_contains($needle, 'tasawuf') => 'Heart',

            str_contains($needle, 'quran'),
            str_contains($needle, 'tafsir') => 'BookMarked',

            str_contains($needle, 'bahasa') => 'Languages',
            str_contains($needle, 'pendidikan') => 'GraduationCap',
            str_contains($needle, 'pengembangan') => 'Brain',
            str_contains($needle, 'sejarah') => 'History',
            str_contains($needle, 'sastra') => 'Feather',
            str_contains($needle, 'sains') => 'FlaskConical',
            str_contains($needle, 'matematika') => 'Calculator',
            str_contains($needle, 'bisnis') => 'Briefcase',
            default => 'BookOpen',
        };
    }
};
