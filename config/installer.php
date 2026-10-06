<?php

return [
    'lock_path' => env(
        'INSTALLER_LOCK_PATH',
        storage_path('app/installed.lock'),
    ),

    'pending_path' => env(
        'INSTALLER_PENDING_PATH',
        storage_path('app/installer/pending.json'),
    ),

    'bootstrap_key_path' => env(
        'INSTALLER_BOOTSTRAP_KEY_PATH',
        storage_path('app/installer/bootstrap.key'),
    ),

    'env_path' => env(
        'INSTALLER_ENV_PATH',
        base_path('.env'),
    ),

    'env_backup_path' => env(
        'INSTALLER_ENV_BACKUP_PATH',
        storage_path('app/installer/.env.backup'),
    ),

    'minimum_php' => '8.3.0',

    'required_extensions' => [
        'ctype',
        'curl',
        'dom',
        'fileinfo',
        'filter',
        'gd',
        'hash',
        'mbstring',
        'openssl',
        'pdo',
        'pdo_mysql',
        'session',
        'sodium',
        'tokenizer',
        'xml',
    ],

    'required_functions' => [
        'proc_open',
        'symlink',
    ],

    'force_uninstalled' => false,
    'force_installed' => false,
];
