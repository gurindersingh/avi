<?php

namespace App\Support\Deployment;

use App\Support\Path;
use App\Concerns\Makeable;
use Illuminate\Pipeline\Pipeline;
use App\Support\Runtimes\RunScript;
use Illuminate\Support\Facades\File;
use App\Support\Runtimes\ReleaseRuntime;
use App\Support\Runtimes\CloneRepoRuntime;
use App\Support\Runtimes\InstallNpmRuntime;
use App\Support\Runtimes\ComposerInstallRuntime;
use App\Support\Runtimes\FilePersmissionsRuntime;
use App\Support\Runtimes\ReloadServicesRuntime;
use App\Support\Runtimes\PreReleaseScriptsRuntime;
use App\Support\Runtimes\PostReleaseScriptsRuntime;
use App\Support\Runtimes\CleanOldDeploymentsRuntime;
use App\Support\Runtimes\ColorsRuntime;
use App\Support\Runtimes\DockerizeRuntime;
use App\Support\Runtimes\MakeRequiredDirectoriesRuntime;

class MultiStackArtifacts
{
    use Makeable;

    public DeploymentDto $dto;

    public string $ip;

    public ?array $preReleaseScripts = null;

    public ?array $postReleaseScripts = null;

    public string $type = 'queueAndTask';

    public string $content = '';

    protected array $info = [
        'ip' => '',
        'directory' => '',
    ];

    public function preReleaseScripts(null|array $scripts): static
    {
        $this->preReleaseScripts = $scripts;
        return $this;
    }

    public function postReleaseScripts(null|array $scripts): static
    {
        $this->postReleaseScripts = $scripts;
        return $this;
    }

    public function ip(string $ip): static
    {
        $this->info['ip'] = $this->ip = $ip;
        return $this;
    }

    public function queueAndTaskServer(): static
    {
        $this->type = 'queueAndTask';
        return $this;
    }

    public function web(): static
    {
        $this->type = 'web';
        return $this;
    }

    public function dto(DeploymentDto $dto): static
    {
        $this->dto = $dto;
        return $this;
    }

    public function makeForServerTypes(): static
    {
        // For load balancer, we don't need to do anything

        // for queue, and task servers, we will keep it same

        $send = [
            'dto' => $this->dto,
            'preReleaseScripts' => $this->preReleaseScripts,
            'postReleaseScripts' => $this->postReleaseScripts,
            'type' => $this->type,
        ];

        $through = [
            ColorsRuntime::class,
            MakeRequiredDirectoriesRuntime::class,
            CloneRepoRuntime::class,
            ComposerInstallRuntime::class,
            // InstallNpmRuntime::class,
            FilePersmissionsRuntime::class,
            PreReleaseScriptsRuntime::class,
            ReleaseRuntime::class,
            PostReleaseScriptsRuntime::class,
            ReloadServicesRuntime::class,
            CleanOldDeploymentsRuntime::class,
        ];

        if ($this->type === 'web' && $this->dto->isFrankephpServer()) {
            $through = [
                ...$through,
                DockerizeRuntime::class,
            ];
        }

        app(Pipeline::class)->send($send)
            ->through($through)
            ->then(function ($data) {
                $this->content = str(implode("\n", array_values($data['content'])))
                    ->replace('&amp;&amp;', '&&')
                    ->toString();
            });

        $this->writeContent();

        // dd('one');

        return $this;
    }

    protected function writeContent()
    {
        $dir = Path::currentDirectory('.avi') . '/multi-stack/' . $this->dto->newRelease . '/' . str($this->ip)->replace('.', '-')->toString();

        File::ensureDirectoryExists($dir);

        File::put($dir . '/run.sh', "#!/bin/bash\n" . RunScript::make()->setDto($this->dto)->process());

        File::put($dir . '/deploy.sh', implode("\n\n", [
            "#!/bin/bash",
            'CURRENT_RELEASE="' . $this->dto->newRelease . '"',
            $this->content
        ]));

        $this->info['directory'] = $dir;

        File::copy(
            Path::currentDirectory('.env.' . $this->dto->stage),
            $dir . '/env'
        );
    }

    public function info(): array
    {
        return $this->info;
    }
}
