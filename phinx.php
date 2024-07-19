<?php

return [
    'paths' => [
        'migrations' => '%%PHINX_CONFIG_DIR%%/db/migrations',
    ],
    'environments' => [
        'default_migration_table' => 'phinxlog',
        'default_environment' => 'development',
        'development' => [
            'adapter' => 'pgsql',
            'host' => '127.0.0.1',
            'name' => 'qwentes',
            'user' => 'postgres',
            'pass' => 'qwentes',
            'port' => 5432,
            'charset' => 'utf8',
        ],
    ],
];
