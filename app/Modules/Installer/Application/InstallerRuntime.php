<?php

namespace App\Modules\Installer\Application;

class InstallerRuntime
{
    public function __construct(
        private readonly InstallerState $state,
    ) {}

    public function bootstrap(): void
    {
        if ($this->state->isInstalled()) {
            return;
        }

        foreach ([
            storage_path('app'),
            storage_path('framework/cache/data'),
            storage_path('framework/sessions'),
            storage_path('framework/views'),
            storage_path('logs'),
        ] as $directory) {
            if (! is_dir($directory)) {
                @mkdir($directory, 0755, true);
            }
        }

        config([
            'app.key' => $this->state->bootstrapKey(),
            'session.driver' => 'file',
            'cache.default' => 'file',
            'queue.default' => 'sync',
        ]);
    }
}
