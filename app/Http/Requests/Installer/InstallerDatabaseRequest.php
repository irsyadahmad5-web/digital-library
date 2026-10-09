<?php

namespace App\Http\Requests\Installer;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class InstallerDatabaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'app_name' => [
                'required',
                'string',
                'max:120',
            ],
            'app_url' => [
                'required',
                'url:http,https',
                'max:255',
            ],
            'timezone' => [
                'required',
                'timezone',
                'max:64',
            ],
            'db_connection' => [
                'required',
                Rule::in(['mysql', 'mariadb']),
            ],
            'db_host' => [
                'required',
                'string',
                'max:255',
                'regex:/^[A-Za-z0-9_.:-]+$/',
            ],
            'db_port' => [
                'required',
                'integer',
                'min:1',
                'max:65535',
            ],
            'db_database' => [
                'required',
                'string',
                'max:64',
                'regex:/^[A-Za-z0-9_$-]+$/',
            ],
            'db_username' => [
                'required',
                'string',
                'max:128',
                'regex:/^[^\x00-\x1F\x7F]+$/',
            ],
            'db_password' => [
                'nullable',
                'string',
                'max:1024',
                'regex:/^[^\\x00-\\x1F\\x7F]*$/u',
            ],
            'trusted_proxies' => [
                'nullable',
                'string',
                'max:1000',
            ],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $url = (string) $this->input('app_url', '');
                $parts = parse_url($url);

                if (
                    is_array($parts)
                    && (
                        isset($parts['user'])
                        || isset($parts['pass'])
                        || isset($parts['query'])
                        || isset($parts['fragment'])
                    )
                ) {
                    $validator->errors()->add(
                        'app_url',
                        'URL utama tidak boleh mengandung credential, query, atau fragment.',
                    );
                }

                if (is_array($parts)) {
                    $scheme = strtolower((string) ($parts['scheme'] ?? ''));
                    $host = strtolower(rtrim((string) ($parts['host'] ?? ''), '.'));
                    $loopback = in_array(
                        $host,
                        ['localhost', '127.0.0.1', '::1'],
                        true,
                    );

                    if ($scheme !== 'https' && ! $loopback) {
                        $validator->errors()->add(
                            'app_url',
                            'URL production wajib menggunakan HTTPS. HTTP hanya diizinkan untuk localhost/loopback.',
                        );
                    }
                }

                $value = trim(
                    (string) $this->input('trusted_proxies', ''),
                );

                if ($value === '') {
                    return;
                }

                foreach (array_filter(array_map('trim', explode(',', $value))) as $proxy) {
                    if (! $this->validProxy($proxy)) {
                        $validator->errors()->add(
                            'trusted_proxies',
                            "Proxy tidak valid: {$proxy}. Gunakan IP atau CIDR, dipisahkan koma.",
                        );

                        return;
                    }
                }
            },
        ];
    }

    protected function prepareForValidation(): void
    {
        $url = trim((string) $this->input('app_url', ''));

        $this->merge([
            'app_name' => trim(
                (string) $this->input('app_name', ''),
            ),
            'app_url' => rtrim($url, '/'),
            'timezone' => trim(
                (string) $this->input('timezone', 'Asia/Jakarta'),
            ),
            'db_host' => trim(
                (string) $this->input('db_host', ''),
            ),
            'db_database' => trim(
                (string) $this->input('db_database', ''),
            ),
            'db_username' => trim(
                (string) $this->input('db_username', ''),
            ),
            'trusted_proxies' => trim(
                (string) $this->input('trusted_proxies', ''),
            ),
        ]);
    }

    private function validProxy(string $value): bool
    {
        if (filter_var($value, FILTER_VALIDATE_IP)) {
            return true;
        }

        if (! str_contains($value, '/')) {
            return false;
        }

        [$ip, $prefix] = array_pad(
            explode('/', $value, 2),
            2,
            '',
        );

        if (! filter_var($ip, FILTER_VALIDATE_IP) || ! ctype_digit($prefix)) {
            return false;
        }

        $bits = str_contains($ip, ':') ? 128 : 32;
        $prefixValue = (int) $prefix;

        return $prefixValue >= 0 && $prefixValue <= $bits;
    }
}
