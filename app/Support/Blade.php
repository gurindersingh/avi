<?php

namespace App\Support;


use Illuminate\Support\Facades\File;

class Blade
{


    public static function make(): static
    {
        $self = new static();

        $self->setConfig();

        return $self;
    }

    public function compile($string, $data = []): string
    {
        $content = $this->parse(is_file($string) ? File::get($string) : $string, $data);

        $this->clean();

        return $content;
    }

    protected function setConfig()
    {
        config()->set('view.paths', [realpath(__DIR__ . '/../../stubs/stubs')]);
        config()->set('view.compiled', realpath(__DIR__ . '/../../stubs/cache'));
        config()->set('cache.stores.file.path', realpath(__DIR__ . '/../../stubs/cache'));
        config()->set('cache.driver', 'file');
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
