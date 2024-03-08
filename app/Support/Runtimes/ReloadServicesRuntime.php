<?php

namespace App\Support\Runtimes;

class ReloadServicesRuntime extends BaseRuntime
{

    protected function process()
    {
        $this->addContent('reload_services', $this->content());
    }


    protected function content(): string
    {
        $string = <<<'BLADE'

sudo service php{{ $phpVersion }}-fpm reload
if sudo supervisorctl version 2>/dev/null; then
    sudo supervisorctl reread
    sudo supervisorctl update
    sudo supervisorctl restart all
fi

BLADE;

        return $this->compileBlade($string, [
            'domain' => $this->dto()->domain,
            'newRelease' => $this->dto()->newRelease,
            'phpVersion' => $this->dto()->php,
        ]);
    }
}
