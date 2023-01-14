<?php

namespace App\Support;

use Illuminate\Support\Facades\File;

class Path
{

    public static function currentDirectory($append = null): string
    {
        return $append ? getcwd() . '/' . $append : getcwd();
    }

    public static function homeDir($append = null): string
    {
        $path = $_SERVER['HOME'] ?? $_SERVER['USERPROFILE'];

        return $append ? "{$path}/{$append}" : $path;
    }

    public static function getBladeCachePath(): string
    {
        File::ensureDirectoryExists(
            Path::currentDirectory('.avi/bladeCache')
        );

        return Path::currentDirectory('.avi/blade-cache');
    }
}
