<?php

namespace App\Support\Runtimes;

class InstallNpmRuntime extends BaseRuntime
{

    protected function process()
    {
        $this->addContent('npm_install', $this->content());
    }


    protected function content(): string
    {
        $string = <<<'BLADE'

cd /home/ubuntu/{{ $domain }}/releases/{{ $newRelease }}

{{ $compilerScript }}

BLADE;

        return $this->compileBlade($string, [
            'domain' => $this->dto()->domain,
            'newRelease' => $this->dto()->newRelease,
            'compilerScript' => $this->compilerScript(),
        ]);
    }

    protected function compilerScript(): string
    {
        return match ($this->dto()->jsCompiler) {
            'pnpm' => 'pnpm install && pnpm run build',
            'bun' => 'bun install && bun run build --mode production',
            default => 'npm install && npm run build'
        };
    }
}
