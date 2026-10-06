<?php

$versionFile = base_path('VERSION');
$version = is_file($versionFile)
    ? trim((string) file_get_contents($versionFile))
    : '0.0.0-dev';

return [
    'version' => env('APP_VERSION', $version),
    'channel' => env('RELEASE_CHANNEL', 'stable'),
];
