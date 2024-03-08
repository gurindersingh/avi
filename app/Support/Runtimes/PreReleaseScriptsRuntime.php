<?php

namespace App\Support\Runtimes;

use Illuminate\Support\Arr;

class PreReleaseScriptsRuntime extends BaseRuntime
{

    protected function process()
    {
        $this->addContent('pre_release_scripts', $this->content());
    }


    protected function scripts(): ?string
    {
        $scripts = $this->dto()->preReleaseScripts;
        $other = $this->data['preReleaseScripts'] ?? [];

        return implode(' && ', [...$scripts, ...$other]);
    }


    protected function content(): string
    {
        $string = <<<'BLADE'

#
# Pre release scripts
#
cd /home/ubuntu/{{ $domain }}/releases/{{ $newRelease }}

{{ $scripts }}


BLADE;

        return $this->compileBlade($string, [
            'domain' => $this->dto()->domain,
            'newRelease' => $this->dto()->newRelease,
            'scripts' => $this->scripts(),
        ]);
    }
}
