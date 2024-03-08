<?php

namespace App\Support\Deployment;

use App\Support\Path;
use App\Concerns\Makeable;
use Illuminate\Support\Arr;
use Symfony\Component\Yaml\Yaml;
use Illuminate\Support\Facades\File;
use App\Support\Deployment\Utilities;

class DeploymentDto
{
    use Makeable, Utilities;

    public array $loadBalancers = [];

    public array $webServers = [];

    public array $queueAndTaskServers = [];

    public int $backup = 3;

    public string $gitToken;

    public ?string $satisHost = null;

    public ?string $satisUsername = null;

    public ?string $satisPassword = null;

    public bool $isFilament = false;

    public function command($command): static
    {
        $this->command = $command;
        return $this;
    }

    public function readConfig(): static
    {
        $this->markNewRelease();

        $this->readFile();

        // dd($this->config);

        $this->domain = $this->config['domain'];

        $this->php = $this->config['php'] ?? '8.2';

        $this->repo = $this->config['repo'];

        $this->gitUser = $this->config['gitUser'];

        $this->jsCompiler = $this->config['jsCompiler'] ?? 'npm';

        $this->gitToken = Arr::get(File::json(Path::homeDir('.composer/auth.json')), 'github-oauth')['github.com'];

        $this->sshKeyPath = $this->configValueByStage('sshKeyPath');

        $this->sshKeyContent = File::get($this->sshKeyPath);

        $this->gitBranch = $this->configValueByStage('gitBranch');

        $this->loadBalancers = $this->configValueByStage('servers.loadBalancers');

        $this->webServers = $this->configValueByStage('servers.web');

        $this->queueAndTaskServers = $this->configValueByStage('servers.queueAndTask');

        $this->preReleaseScripts = $this->configValueByStage('preReleaseScripts', []) ?: [];

        $this->postReleaseScripts = $this->configValueByStage('postReleaseScripts', []) ?: [];

        $this->octaneType = $this->configValueByStage('octane.provider');

        $this->uniqueCommandsPerIp = $this->configValueByStage('setupScripts.uniqueCommandsPerIp');

        $this->isFilament = ($this->config['filament'] ?? null) === true;

        $this->satisHost = Arr::get($this->config, 'satis.host');

        if ($satis = Arr::get(File::json(Path::homeDir('.composer/auth.json')), 'http-basic')[$this->satisHost]) {
            $this->satisHost = 'https://' . $this->satisHost;

            $this->satisUsername = Arr::get($satis, 'username');

            $this->satisPassword = Arr::get($satis, 'password');
        }

        $this->ensureEnvFileExist();

        return $this;
    }

    protected function readFile()
    {
        $this->ensureConfigFileExist();

        $this->parseStage();

        $this->config = Yaml::parse(
            File::get(Path::currentDirectory('avi.yml'))
        );
    }

    public function toArray(): array
    {
        return [
            'newRelease' => $this->newRelease,
            'domain' => $this->domain,
            'php' => $this->php,
            'jsCompiler' => $this->jsCompiler,
            'stage' => $this->stage,
            'repo' => $this->repo,
            'gitBranch' => $this->gitBranch,
            'gitUser' => $this->gitUser,
            'gitToken' => $this->gitToken,
            'sshKeyPath' => $this->sshKeyPath,
            'sshKeyContent' => $this->sshKeyContent,
            'octaneType' => $this->octaneType,
            'loadBalancers' => $this->loadBalancers,
            'webServers' => $this->webServers,
            'queueAndTaskServers' => $this->queueAndTaskServers,
            'backup' => $this->backup,
            'satisHost' => $this->satisHost,
            'satisUsername' => $this->satisUsername,
            'satisPassword' => $this->satisPassword,
            'isFilament' => $this->isFilament,
            'preReleaseScripts' => $this->preReleaseScripts,
            'postReleaseScripts' => $this->postReleaseScripts,
        ];
    }

    public function isFrankephpServer(): bool
    {
        return $this->octaneType === 'frankenphp';
    }
}
