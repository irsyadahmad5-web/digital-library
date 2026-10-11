<?php

$versionFile = base_path('VERSION');
$version = is_file($versionFile)
    ? trim((string) file_get_contents($versionFile))
    : '0.0.0-dev';

$defaultChannel = match (true) {
    str_contains($version, '-rc') => 'rc',
    str_contains($version, '-beta') => 'beta',
    str_contains($version, '-alpha') => 'alpha',
    default => 'stable',
};

return [
    'version' => env('APP_VERSION', $version),
    'channel' => env('RELEASE_CHANNEL', $defaultChannel),
];
