<?php

namespace App\Concerns;

trait Makeable
{
    public static function make(...$args): static
    {
        return new static(...$args);
    }

    public static function singleton(...$args): static
    {
        if (app()->has(static::class)) return resolve(static::class);
        app()->instance(static::class, new static(...$args));
        return resolve(static::class);
    }
}
