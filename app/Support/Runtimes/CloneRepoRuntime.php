<?php

namespace App\Support\Runtimes;

class CloneRepoRuntime extends BaseRuntime
{

    protected function process()
    {
        $this->addContent('clone_repo', $this->content());
    }


    protected function content(): string
    {
        $string = <<<'BLADE'

cd /home/ubuntu/{{ $domain }}/releases/{{ $newRelease }}
@if($sshKeyContent)
GIT_SSH_COMMAND='ssh -i /home/ubuntu/{{ $domain }}/deployments/{{ $newRelease }}/id_rsa -o IdentitiesOnly=yes' git clone {{ $repo }} .
@elseif($gitToken)
COMPOSER_AUTH='{"github-oauth": {"github.com": "{{ $gitToken }}"}}' \
    GH_TOKEN="{{ $gitToken }}" \
    gh repo clone {{ $repo }} .
@else
git clone {{ $repo }} .
@endif
git checkout {{ $gitBranch }}

cd /home/ubuntu/{{ $domain }}/releases/{{ $newRelease }}

BLADE;

        return $this->compileBlade($string, [
            'domain' => $this->dto()->domain,
            'newRelease' => $this->dto()->newRelease,
            'sshKeyContent' => $this->dto()->sshKeyContent,
            'gitUser' => $this->dto()->gitUser,
            'repo' => $this->dto()->repo,
            'gitToken' => $this->dto()->gitToken,
            'gitBranch' => $this->dto()->gitBranch,
        ]);
    }
}
