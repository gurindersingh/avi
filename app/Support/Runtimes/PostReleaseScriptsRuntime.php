<?php

namespace App\Support\Runtimes;

class PostReleaseScriptsRuntime extends BaseRuntime
{

    protected function process()
    {
        $this->addContent('post_release_scripts', $this->content());
    }

    protected function scripts(): ?string
    {
        $scripts = $this->dto()->postReleaseScripts;
        $other = $this->data['postReleaseScripts'] ?? [];

        return implode(' && ', [...$scripts, ...$other]);
    }


    protected function content(): string
    {
        $string = <<<'BLADE'

#
# POST release scripts
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

    // protected function scripts(): ?string
    // {
    //     return implode(' && ', $this->dto()->postReleaseScripts);
    // }
}
