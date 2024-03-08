<?php

namespace App\Support\Runtimes;

class ReleaseRuntime extends BaseRuntime
{

    protected function process()
    {
        $this->addContent('release', $this->content());
    }


    protected function content(): string
    {
        $string = <<<'BLADE'

cd /home/ubuntu/{{ $domain }}/releases/{{ $newRelease }}

rm -rf ./storage
ln -sfn /home/ubuntu/{{ $domain }}/storage .
ln -sfn /home/ubuntu/{{ $domain }}/releases/{{ $newRelease }} /home/ubuntu/{{ $domain }}/current
BLADE;

        return $this->compileBlade($string, [
            'domain' => $this->dto()->domain,
            'newRelease' => $this->dto()->newRelease,
        ]);
    }
}
