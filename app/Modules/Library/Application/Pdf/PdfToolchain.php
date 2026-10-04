<?php

namespace App\Modules\Library\Application\Pdf;

use DomainException;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

class PdfToolchain
{
    /**
     * @return array<string, mixed>
     */
    public function inspect(string $absolutePath): array
    {
        $binary = $this->resolveBinary((string) config('pdf.pdfinfo_binary', 'pdfinfo'));

        $process = new Process([
            $binary,
            '-enc',
            'UTF-8',
            $absolutePath,
        ]);
        $process->setTimeout(max(5, (int) config('pdf.inspect_timeout_seconds', 45)));
        $process->run();

        if (! $process->isSuccessful()) {
            throw new DomainException(
                'pdfinfo gagal membaca PDF: '.$this->cleanError($process->getErrorOutput()),
            );
        }

        $metadata = $this->parsePdfInfo($process->getOutput());

        $pages = (int) ($metadata['pages'] ?? 0);

        if ($pages < 1) {
            throw new DomainException('Jumlah halaman PDF tidak dapat ditentukan.');
        }

        if (($metadata['encrypted'] ?? false) === true) {
            throw new DomainException('PDF terenkripsi/password-protected belum didukung.');
        }

        return $metadata;
    }

    public function renderFirstPage(string $absolutePdfPath, string $absoluteOutputPrefix): string
    {
        $binary = $this->resolveBinary((string) config('pdf.pdftocairo_binary', 'pdftocairo'));
        $width = max(320, min(1600, (int) config('pdf.preview_width', 720)));
        $quality = max(50, min(95, (int) config('pdf.preview_jpeg_quality', 85)));

        $process = new Process([
            $binary,
            '-jpeg',
            '-singlefile',
            '-f',
            '1',
            '-l',
            '1',
            '-scale-to-x',
            (string) $width,
            '-scale-to-y',
            '-1',
            '-jpegopt',
            'quality='.$quality,
            $absolutePdfPath,
            $absoluteOutputPrefix,
        ]);
        $process->setTimeout(max(10, (int) config('pdf.render_timeout_seconds', 90)));
        $process->run();

        if (! $process->isSuccessful()) {
            throw new DomainException(
                'Thumbnail halaman pertama gagal dibuat: '.$this->cleanError($process->getErrorOutput()),
            );
        }

        $output = $absoluteOutputPrefix.'.jpg';

        if (! is_file($output) || (int) filesize($output) === 0) {
            throw new DomainException('Thumbnail PDF tidak ditemukan setelah proses render.');
        }

        return $output;
    }

    /**
     * @return array<string, string|false>
     */
    public function availability(): array
    {
        return [
            'pdfinfo' => $this->findBinary((string) config('pdf.pdfinfo_binary', 'pdfinfo')),
            'pdftocairo' => $this->findBinary((string) config('pdf.pdftocairo_binary', 'pdftocairo')),
        ];
    }

    private function resolveBinary(string $binary): string
    {
        $resolved = $this->findBinary($binary);

        if (! is_string($resolved) || $resolved === '') {
            throw new DomainException("Binary PDF tidak tersedia: {$binary}.");
        }

        return $resolved;
    }

    private function findBinary(string $binary): string|false
    {
        if (str_contains($binary, DIRECTORY_SEPARATOR) && is_executable($binary)) {
            return $binary;
        }

        return (new ExecutableFinder)->find($binary, false);
    }

    /**
     * @return array<string, mixed>
     */
    private function parsePdfInfo(string $output): array
    {
        $raw = [];

        foreach (preg_split('/\R/u', trim($output)) ?: [] as $line) {
            if (! str_contains($line, ':')) {
                continue;
            }

            [$key, $value] = array_map('trim', explode(':', $line, 2));

            if ($key === '') {
                continue;
            }

            $raw[$key] = $value;
        }

        return [
            'title' => $raw['Title'] ?? null,
            'author' => $raw['Author'] ?? null,
            'subject' => $raw['Subject'] ?? null,
            'keywords' => $raw['Keywords'] ?? null,
            'creator' => $raw['Creator'] ?? null,
            'producer' => $raw['Producer'] ?? null,
            'creation_date' => $raw['CreationDate'] ?? null,
            'modification_date' => $raw['ModDate'] ?? null,
            'tagged' => $this->yesNo($raw['Tagged'] ?? null),
            'user_properties' => $this->yesNo($raw['UserProperties'] ?? null),
            'suspects' => $this->yesNo($raw['Suspects'] ?? null),
            'form' => $raw['Form'] ?? null,
            'javascript' => $this->yesNo($raw['JavaScript'] ?? null),
            'pages' => isset($raw['Pages']) && is_numeric($raw['Pages'])
                ? (int) $raw['Pages']
                : null,
            'encrypted' => isset($raw['Encrypted'])
                ? str_starts_with(strtolower($raw['Encrypted']), 'yes')
                : null,
            'page_size' => $raw['Page size'] ?? null,
            'page_rotation' => isset($raw['Page rot']) && is_numeric($raw['Page rot'])
                ? (int) $raw['Page rot']
                : null,
            'file_size' => $raw['File size'] ?? null,
            'optimized' => $this->yesNo($raw['Optimized'] ?? null),
            'pdf_version' => $raw['PDF version'] ?? null,
        ];
    }

    private function yesNo(?string $value): ?bool
    {
        if ($value === null) {
            return null;
        }

        return match (strtolower(trim($value))) {
            'yes' => true,
            'no' => false,
            default => null,
        };
    }

    private function cleanError(string $error): string
    {
        $error = trim(preg_replace('/\s+/u', ' ', $error) ?? '');

        return $error !== '' ? mb_substr($error, 0, 500) : 'unknown error';
    }
}
