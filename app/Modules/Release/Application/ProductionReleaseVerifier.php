<?php

namespace App\Modules\Release\Application;

use App\Modules\Operations\Application\OperationsHealth;
use App\Modules\Operations\Application\RestoreReadiness;
use App\Modules\Quality\Application\ReleaseQualityGate;

class ProductionReleaseVerifier
{
    public function __construct(
        private readonly ReleaseQualityGate $quality,
        private readonly OperationsHealth $health,
        private readonly RestoreReadiness $restore,
    ) {}

    /**
     * @return array{
     *     passed: bool,
     *     version: string,
     *     channel: string,
     *     checked_at: string,
     *     checks: array<int, array{
     *         key: string,
     *         passed: bool,
     *         message: string
     *     }>,
     *     quality: array<string, mixed>,
     *     health: array<string, mixed>,
     *     restore: array<string, mixed>|null
     * }
     */
    public function verify(bool $freshInstall = false): array
    {
        $version = trim((string) config(
            'release.version',
            '0.0.0-dev',
        ));
        $channel = trim((string) config(
            'release.channel',
            'stable',
        ));

        $quality = $this->quality->verify(true);
        $health = $this->health->report();
        $restore = $freshInstall
            ? null
            : $this->restore->check();

        $checks = [
            $this->check(
                'version',
                preg_match(
                    '/^(0|[1-9]\d*)\.(0|[1-9]\d*)\.(0|[1-9]\d*)(?:-[0-9A-Za-z.-]+)?$/',
                    $version,
                ) === 1,
                "Release version: {$version}.",
            ),
            $this->check(
                'channel',
                in_array(
                    $channel,
                    ['stable', 'rc', 'beta', 'alpha'],
                    true,
                ),
                "Release channel: {$channel}.",
            ),
            $this->check(
                'quality',
                (bool) ($quality['passed'] ?? false),
                ($quality['passed'] ?? false)
                    ? 'Production quality gate passed.'
                    : 'Production quality gate failed.',
            ),
            $this->check(
                'operations',
                (int) ($health['counts']['critical'] ?? 1) === 0,
                'Operational health: '
                    .strtoupper((string) ($health['status'] ?? 'unknown'))
                    .'.',
            ),
            $this->check(
                'restore',
                $freshInstall
                    || (bool) ($restore['ready'] ?? false),
                $freshInstall
                    ? 'Backup prerequisite waived for a confirmed fresh install.'
                    : (($restore['ready'] ?? false)
                        ? 'Latest backup is restore-ready.'
                        : 'Latest backup is not restore-ready.'),
            ),
        ];

        return [
            'passed' => collect($checks)
                ->every(
                    fn (array $check): bool => $check['passed'],
                ),
            'version' => $version,
            'channel' => $channel,
            'checked_at' => now()->toIso8601String(),
            'checks' => $checks,
            'quality' => $quality,
            'health' => $health,
            'restore' => $restore,
        ];
    }

    /**
     * @return array{key: string, passed: bool, message: string}
     */
    private function check(
        string $key,
        bool $passed,
        string $message,
    ): array {
        return compact('key', 'passed', 'message');
    }
}
