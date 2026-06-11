<?php

declare(strict_types=1);

use App\Enums\FilesystemDiskEnum;
use Thinkycz\LaravelCore\Support\Env;
use Thinkycz\LaravelCore\Support\Resolver;

$app = Resolver::resolveApp();
$env = Env::inject();

return [
    /*
    |--------------------------------------------------------------------------
    | Default Filesystem Disk
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default filesystem disk that should be used
    | by the framework. The "local" disk, as well as a variety of cloud
    | based disks are available to your application for file storage.
    |
    */

    'default' => FilesystemDiskEnum::Local->value,

    /*
    |--------------------------------------------------------------------------
    | Filesystem Disks
    |--------------------------------------------------------------------------
    |
    | Below you may configure as many filesystem disks as necessary, and you
    | may even configure multiple disks for the same driver. Examples for
    | most supported storage drivers are configured here for reference.
    |
    | Supported drivers: "local", "ftp", "sftp", "s3"
    |
    */

    'disks' => [
        FilesystemDiskEnum::Local->value => [
            'driver' => 'local',
            'root' => $app->storagePath('app'),
            'throw' => true,
        ],

        FilesystemDiskEnum::Public->value => [
            'driver' => 'local',
            'root' => $app->storagePath('app/public'),
            'url' => $env->mustParseString('APP_URL') . '/storage',
            'visibility' => 'public',
            'throw' => false,
        ],

        FilesystemDiskEnum::Private->value => [
            'driver' => 'local',
            'root' => $app->storagePath('app/private'),
            'url' => $env->mustParseString('APP_URL') . '/private-storage',
            'visibility' => 'private',
            'serve' => true,
            'throw' => false,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Symbolic Links
    |--------------------------------------------------------------------------
    |
    | Here you may configure the symbolic links that will be created when the
    | `storage:link` Artisan command is executed. The array keys should be
    | the locations of the links and the values should be their targets.
    |
    */

    'links' => [
        $app->publicPath('storage') => $app->storagePath('app/public'),
    ],
];
