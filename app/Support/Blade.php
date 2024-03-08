<?php

namespace App\Support;

use App\Concerns\Makeable;
use Illuminate\Support\Facades\File;

class Blade
{
    use Makeable;

    public function __construct()
    {
        config()->set('view.paths', [realpath(__DIR__ . '/../../stubs/stubs')]);
        config()->set('view.compiled', Path::getBladeCachePath());
        config()->set('cache.stores.file.path', Path::getBladeCachePath());
        config()->set('cache.driver', 'file');
    }

    public function compile($string, $data = []): string
    {
        return $this->parse(is_file($string) ? File::get($string) : $string, $data);
    }

    protected function parse(string $string, array $data): string
    {
        return \Illuminate\Support\Facades\Blade::render($string, $data);
    }

    protected function clean()
    {
        File::deleteDirectory(config('view.compiled'));
        File::makeDirectory(config('view.compiled'));
    }

}
