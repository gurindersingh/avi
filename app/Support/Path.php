<?php

namespace App\Support;

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
}
