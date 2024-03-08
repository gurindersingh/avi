<?php

namespace App\Support\Runtimes;

class MakeRequiredDirectoriesRuntime extends BaseRuntime
{

    protected function process()
    {
        $this->addContent('initial_files', $this->content());
    }


    protected function content(): string
    {
        $string = <<<'BLADE'
mkdir -p /home/ubuntu/{{ $domain }}/{releases,storage,deployments}
mkdir -p /home/ubuntu/{{ $domain }}/storage/{app/public,logs,framework/cache/data,framework/sessions,framework/testing,framework/views}
mkdir -p /home/ubuntu/{{ $domain }}/releases/{{ $newRelease }}

rm -rf ~/.ssh/known_hosts && \
    ssh-keyscan -H github.com >> ~/.ssh/known_hosts && \
	ssh-keyscan -H bitbucket.org >> ~/.ssh/known_hosts && \
	ssh-keyscan -H gitlab.com >> ~/.ssh/known_hosts

cat > /home/ubuntu/{{ $domain }}/deployments/{{ $newRelease }}/id_rsa << EOF
{{ $sshKeyContent }}
EOF

sudo chmod 600 /home/ubuntu/{{ $domain }}/deployments/{{ $newRelease }}/id_rsa
rm -rf /home/ubuntu/.ssh/config

cat > /home/ubuntu/.ssh/config << EOF
Host github.com
	User {{ $gitUser }}
	Hostname github.com
	PreferredAuthentications publickey
	IdentityFile /home/ubuntu/{{ $domain }}/deployments/{{ $newRelease }}/id_rsa
EOF

sudo chmod -R 700 /home/ubuntu/.ssh
sudo chmod -R 644 /home/ubuntu/.ssh/id_server.pub
sudo chmod -R 600 /home/ubuntu/.ssh/id_server
sudo chmod -R 600 /home/ubuntu/.ssh/config
sudo chmod -R 600 /home/ubuntu/.ssh/known_hosts
sudo chmod -R 600 /home/ubuntu/.ssh/authorized_keys

BLADE;

        return $this->compileBlade($string, [
            'domain' => $this->dto()->domain,
            'newRelease' => $this->dto()->newRelease,
            'sshKeyContent' => $this->dto()->sshKeyContent,
            'gitUser' => $this->dto()->gitUser,
        ]);
    }
}
