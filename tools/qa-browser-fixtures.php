<?php

declare(strict_types=1);

use App\Modules\Library\Domain\Models\Ebook;
use App\Modules\Library\Domain\Models\EbookFile;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Storage;

require dirname(__DIR__).'/vendor/autoload.php';

$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

function qaPdf(string $label): string
{
    $stream = "BT\n/F1 24 Tf\n72 720 Td\n(".$label.") Tj\nET\n";

    $objects = [
        "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n",
        "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n",
        "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>\nendobj\n",
        "4 0 obj\n<< /Length ".strlen($stream)." >>\nstream\n".$stream."endstream\nendobj\n",
        "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n",
    ];

    $pdf = "%PDF-1.4\n";
    $offsets = [0];

    foreach ($objects as $object) {
        $offsets[] = strlen($pdf);
        $pdf .= $object;
    }

    $xrefOffset = strlen($pdf);
    $pdf .= "xref\n0 6\n";
    $pdf .= "0000000000 65535 f \n";

    foreach (array_slice($offsets, 1) as $offset) {
        $pdf .= str_pad((string) $offset, 10, '0', STR_PAD_LEFT)." 00000 n \n";
    }

    $pdf .= "trailer\n<< /Size 6 /Root 1 0 R >>\n";
    $pdf .= "startxref\n".$xrefOffset."\n%%EOF\n";

    return $pdf;
}

$books = [
    [
        'title' => 'QA Reader Book',
        'slug' => 'qa-reader-book',
        'description' => 'Ebook fixture untuk menguji reader portrait, landscape, detail ebook, dan metadata minimal.',
    ],
    [
        'title' => 'Panduan Perpustakaan Digital dengan Judul Panjang untuk Menguji Responsivitas Tata Letak dan Pemenggalan Konten pada Berbagai Ukuran Layar',
        'slug' => 'qa-long-content-book',
        'description' => str_repeat('Konten panjang untuk menguji tata letak responsif tanpa merusak hierarchy visual. ', 8),
    ],
];

foreach ($books as $index => $data) {
    $ebook = Ebook::query()->updateOrCreate(
        ['slug' => $data['slug']],
        [
            'title' => $data['title'],
            'description' => $data['description'],
            'publication_status' => 'published',
            'read_enabled' => true,
            'download_enabled' => true,
            'published_at' => now(),
            'page_count' => 1,
        ],
    );

    $pdf = qaPdf('Ladunni QA '.($index + 1));
    $path = 'ebooks/'.$ebook->getKey().'/qa-browser.pdf';

    Storage::disk('local')->put($path, $pdf);

    EbookFile::query()->updateOrCreate(
        ['ebook_id' => $ebook->getKey()],
        [
            'source_type' => 'local',
            'disk' => 'local',
            'path' => $path,
            'original_name' => 'qa-browser.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => strlen($pdf),
            'sha256' => hash('sha256', $pdf),
            'verification_status' => 'verified',
            'processing_status' => 'processed',
            'page_count' => 1,
            'processed_at' => now(),
            'verified_at' => now(),
        ],
    );
}

fwrite(STDOUT, "Browser QA fixtures: ready\n");
