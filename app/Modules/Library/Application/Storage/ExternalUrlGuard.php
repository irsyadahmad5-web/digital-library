<?php

namespace App\Modules\Library\Application\Storage;

use DomainException;

class ExternalUrlGuard
{
    public function assertAllowed(string $url, bool $httpsOnly): string
    {
        return $this->inspect($url, $httpsOnly)['url'];
    }

    /**
     * @return array{url: string, options: array<string, mixed>}
     */
    public function requestOptions(string $url, bool $httpsOnly): array
    {
        $target = $this->inspect($url, $httpsOnly);

        if ($target['ip_literal']) {
            return [
                'url' => $target['url'],
                'options' => [],
            ];
        }

        if (! defined('CURLOPT_RESOLVE')) {
            throw new DomainException(
                'cURL dengan dukungan DNS pinning diperlukan untuk URL eksternal.',
            );
        }

        $ip = $target['ips'][0] ?? null;

        if (! is_string($ip) || $ip === '') {
            throw new DomainException('Host eksternal tidak memiliki alamat publik.');
        }

        $address = str_contains($ip, ':') ? '['.$ip.']' : $ip;
        $entry = $target['host'].':'.$target['port'].':'.$address;

        return [
            'url' => $target['url'],
            'options' => [
                'curl' => [
                    CURLOPT_RESOLVE => [$entry],
                ],
            ],
        ];
    }

    /**
     * @return array{
     *     url: string,
     *     scheme: string,
     *     host: string,
     *     port: int,
     *     ip_literal: bool,
     *     ips: array<int, string>
     * }
     */
    private function inspect(string $url, bool $httpsOnly): array
    {
        $url = trim($url);

        if (
            $url === ''
            || preg_match('/[\x00-\x20\x7F]/', $url)
        ) {
            throw new DomainException('URL eksternal tidak valid.');
        }

        $parts = parse_url($url);

        if (! is_array($parts)) {
            throw new DomainException('URL eksternal tidak valid.');
        }

        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $rawHost = (string) ($parts['host'] ?? '');
        $host = strtolower(trim($rawHost, '[]'));

        if (
            $host === ''
            || ! in_array($scheme, ['http', 'https'], true)
        ) {
            throw new DomainException(
                'URL harus menggunakan HTTP atau HTTPS dan memiliki host yang valid.',
            );
        }

        if ($httpsOnly && $scheme !== 'https') {
            throw new DomainException(
                'Pengaturan storage hanya mengizinkan URL HTTPS.',
            );
        }

        if (isset($parts['user']) || isset($parts['pass'])) {
            throw new DomainException(
                'URL yang mengandung username atau password tidak diizinkan.',
            );
        }

        if (str_ends_with($rawHost, '.') || str_contains($host, '%')) {
            throw new DomainException('Format host eksternal tidak diizinkan.');
        }

        $port = isset($parts['port'])
            ? (int) $parts['port']
            : ($scheme === 'https' ? 443 : 80);
        $allowedPorts = $scheme === 'https' ? [443] : [80];

        if (! in_array($port, $allowedPorts, true)) {
            throw new DomainException('Port URL eksternal tidak diizinkan.');
        }

        if (
            $host === 'localhost'
            || str_ends_with($host, '.localhost')
            || str_ends_with($host, '.local')
            || str_ends_with($host, '.internal')
        ) {
            throw new DomainException('Host lokal/internal tidak diizinkan.');
        }

        if (filter_var($host, FILTER_VALIDATE_IP)) {
            $this->assertPublicIp($host);

            return [
                'url' => $url,
                'scheme' => $scheme,
                'host' => $host,
                'port' => $port,
                'ip_literal' => true,
                'ips' => [$host],
            ];
        }

        if (
            preg_match('/^0x[0-9a-f]+$/i', $host)
            || preg_match('/^\d+$/', $host)
            || preg_match('/^[0-9.]+$/', $host)
        ) {
            throw new DomainException('Format host numerik tidak diizinkan.');
        }

        if (! preg_match('/^[a-z0-9.-]{1,253}$/i', $host)) {
            throw new DomainException('Format hostname eksternal tidak valid.');
        }

        $ips = $this->resolvePublicIps($host);

        if ($ips === []) {
            throw new DomainException(
                'Hostname eksternal tidak dapat di-resolve ke alamat publik.',
            );
        }

        return [
            'url' => $url,
            'scheme' => $scheme,
            'host' => $host,
            'port' => $port,
            'ip_literal' => false,
            'ips' => $ips,
        ];
    }

    /**
     * @return array<int, string>
     */
    private function resolvePublicIps(string $host): array
    {
        $ips = [];
        $records = @dns_get_record($host, DNS_A | DNS_AAAA);

        if (is_array($records)) {
            foreach ($records as $record) {
                $ip = $record['ip'] ?? $record['ipv6'] ?? null;

                if (is_string($ip) && $ip !== '') {
                    $ips[] = $ip;
                }
            }
        }

        if ($ips === []) {
            $ipv4 = @gethostbynamel($host);

            if (is_array($ipv4)) {
                $ips = [...$ips, ...$ipv4];
            }
        }

        $ips = array_values(array_unique($ips));

        foreach ($ips as $ip) {
            $this->assertPublicIp($ip);
        }

        return $ips;
    }

    private function assertPublicIp(string $ip): void
    {
        $valid = filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE,
        );

        if ($valid === false) {
            throw new DomainException(
                'URL mengarah ke jaringan private/reserved dan ditolak.',
            );
        }
    }
}
