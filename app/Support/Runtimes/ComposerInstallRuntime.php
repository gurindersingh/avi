<?php

namespace App\Support\Runtimes;

class ComposerInstallRuntime extends BaseRuntime
{

    protected function process()
    {
        $this->addContent(
            'composer_install',
            str($this->content())
                ->replace("&quot;", '"')
                ->replace("\/", '/')
                ->toString()
        );
    }


    protected function content(): string
    {
        $string = <<<'BLADE'
echo_green #
echo_green # Composer install
echo_green #
cd /home/ubuntu/{{ $domain }}/releases/{{ $newRelease }}
mkdir -p ./storage/{app/public,logs,framework/cache,framework/sessions,framework/testing,framework/views}

cd /home/ubuntu/{{ $domain }}/releases/{{ $newRelease }}

cp \
    /home/ubuntu/{{ $domain }}/deployments/{{ $newRelease }}/env \
    /home/ubuntu/{{ $domain }}/releases/{{ $newRelease }}/.env

#  -vvv --profile
COMPOSER_AUTH='{{ $composerAuth }}' composer install --prefer-dist --optimize-autoloader --no-dev --no-scripts \
    --ignore-platform-reqs

BLADE;

        return $this->compileBlade($string, [
            'domain' => $this->dto()->domain,
            'newRelease' => $this->dto()->newRelease,
            'sshKeyContent' => $this->dto()->sshKeyContent,
            'gitUser' => $this->dto()->gitUser,
            'repo' => $this->dto()->repo,
            'gitToken' => $this->dto()->gitToken,
            'gitBranch' => $this->dto()->gitBranch,
            'composerAuth' => $this->composerAuth(),
        ]);
    }

    protected function composerAuth()
    {
        return collect([
            'bitbucket-oauth' => [],
            'github-oauth' => [
                'github.com' => $this->dto()->gitToken,
            ],
            'gitlab-token' => [],
            'bearer' => [],
            ...$this->satisAuth(),
        ])->filter(fn ($val, $key) => filled($val))->toJson();
    }

    protected function satisAuth(): array
    {
        $auth = [];

        if ($this->dto()->satisHost) {
            $auth = [
                'http-basic' => [
                    $this->dto()->satisHost => [
                        'username' => $this->dto()->satisUsername,
                        'password' => $this->dto()->satisPassword,
                    ],
                ],
            ];
        }

        return $auth;
    }
}
