<?php

namespace App\Modules\Library\Application\Storage;

use DomainException;

class ExternalUrlGuard
{
    public function assertAllowed(string $url, bool $httpsOnly): string
    {
        $url = trim($url);
        $parts = parse_url($url);

        if (! is_array($parts)) {
            throw new DomainException('URL eksternal tidak valid.');
        }

        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = strtolower(rtrim((string) ($parts['host'] ?? ''), '.'));

        if ($host === '' || ! in_array($scheme, ['http', 'https'], true)) {
            throw new DomainException('URL harus menggunakan HTTP atau HTTPS dan memiliki host yang valid.');
        }

        if ($httpsOnly && $scheme !== 'https') {
            throw new DomainException('Pengaturan storage hanya mengizinkan URL HTTPS.');
        }

        if (isset($parts['user']) || isset($parts['pass'])) {
            throw new DomainException('URL yang mengandung username atau password tidak diizinkan.');
        }

        if (isset($parts['port'])) {
            $allowedPorts = $scheme === 'https' ? [443] : [80];

            if (! in_array((int) $parts['port'], $allowedPorts, true)) {
                throw new DomainException('Port URL eksternal tidak diizinkan.');
            }
        }

        if (
            $host === 'localhost'
            || str_ends_with($host, '.localhost')
            || str_ends_with($host, '.local')
            || str_ends_with($host, '.internal')
        ) {
            throw new DomainException('Host lokal/internal tidak diizinkan.');
        }

        $normalizedHost = trim($host, '[]');

        if (
            preg_match('/^0x[0-9a-f]+$/i', $normalizedHost)
            || preg_match('/^\d+$/', $normalizedHost)
        ) {
            throw new DomainException('Format host numerik tidak diizinkan.');
        }

        if (filter_var($normalizedHost, FILTER_VALIDATE_IP)) {
            $this->assertPublicIp($normalizedHost);

            return $url;
        }

        $records = @dns_get_record($normalizedHost, DNS_A | DNS_AAAA);

        if (is_array($records)) {
            foreach ($records as $record) {
                $ip = $record['ip'] ?? $record['ipv6'] ?? null;

                if (is_string($ip) && $ip !== '') {
                    $this->assertPublicIp($ip);
                }
            }
        }

        return $url;
    }

    private function assertPublicIp(string $ip): void
    {
        $valid = filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE,
        );

        if ($valid === false) {
            throw new DomainException('URL mengarah ke jaringan private/reserved dan ditolak.');
        }
    }
}
