<?php

namespace App\Support\Runtimes;

use App\Concerns\Makeable;

class RunScript extends BaseRuntime
{
    public function process()
    {
        return $this->compile();
    }

    protected function compile(): string
    {
        $string = <<<'BLADE'
cd /home/ubuntu/{{ $domain }}/deployments/{{ $newRelease }}

zsh ./deploy.sh 2>&1 | tee ./output.log

BLADE;

        return $this->compileBlade($string, [
            'domain' => $this->dto()->domain,
            'newRelease' => $this->dto()->newRelease,
        ]);;
    }
}
