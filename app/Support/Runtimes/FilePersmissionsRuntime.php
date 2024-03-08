<?php

namespace App\Support\Runtimes;

class FilePersmissionsRuntime extends BaseRuntime
{

    protected function process()
    {
        $this->addContent('file_permissions', $this->content());
    }


    protected function content(): string
    {
        $string = <<<'BLADE'

cd /home/ubuntu/{{ $domain }}/releases/{{ $newRelease }}

sudo chown -R ubuntu:www-data /home/ubuntu/{{ $domain }}

sudo chgrp -R www-data /home/ubuntu/{{ $domain }}/storage
sudo chgrp -R www-data /home/ubuntu/{{ $domain }}/releases/{{ $newRelease }}/bootstrap/cache

sudo chmod -R ug+rwx /home/ubuntu/{{ $domain }}/storage
sudo chmod -R ug+rwx /home/ubuntu/{{ $domain }}/releases/{{ $newRelease }}/bootstrap/cache

sudo chown -R ubuntu:www-data /home/ubuntu/{{ $domain }}

chmod -R 775 /home/ubuntu/{{ $domain }}/storage/framework
chmod -R 775 /home/ubuntu/{{ $domain }}/storage/logs
chmod -R 775 /home/ubuntu/{{ $domain }}/releases/{{ $newRelease }}/bootstrap/cache

sudo chmod 600 /home/ubuntu/{{ $domain }}/deployments/{{ $newRelease }}/id_rsa

BLADE;

        return $this->compileBlade($string, [
            'domain' => $this->dto()->domain,
            'newRelease' => $this->dto()->newRelease,
        ]);
    }
}
