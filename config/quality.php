<?php

return [
    'required_public_files' => [
        'build/manifest.json',
        'sw.js',
        'offline.html',
        'pwa/apple-touch-icon.png',
        'pwa/icon-192.png',
        'pwa/icon-512.png',
        'pwa/icon-maskable-512.png',
    ],

    'writable_paths' => [
        storage_path('app'),
        storage_path('framework'),
        storage_path('logs'),
        base_path('bootstrap/cache'),
    ],

    'pwa_icons' => [
        'pwa/apple-touch-icon.png' => [180, 180],
        'pwa/icon-192.png' => [192, 192],
        'pwa/icon-512.png' => [512, 512],
        'pwa/icon-maskable-512.png' => [512, 512],
    ],
];
