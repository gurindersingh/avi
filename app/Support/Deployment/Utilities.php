<?php

namespace App\Support\Deployment;

use App\Support\Path;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\File;
use LaravelZero\Framework\Commands\Command;

trait Utilities
{
    protected Command $command;

    public string $newRelease;

    protected array $config;

    public string $domain;

    public string $jsCompiler;

    public string $php;

    public string $stage;

    public string $repo;

    public string $gitBranch;

    public string $gitUser;

    public string $sshKeyPath;

    public string $sshKeyContent;

    public null|string $octaneType = null;

    public array $preReleaseScripts = [];

    public array $postReleaseScripts = [];

    public array $uniqueCommandsPerIp = [];

    public function command(Command $command): static
    {
        $this->command = $command;
        return $this;
    }

    protected function exitWithError($error): void
    {
        $this->command->error($error);
        exit(0);
    }

    public function markNewRelease(): void
    {
        $this->newRelease = str(now()->timezone('America/Toronto')->toDateTimeString())->replace(' ', '_')->replace(':', '-')->toString();
    }

    public function ensureConfigFileExist()
    {
        if (!File::exists(Path::currentDirectory('avi.yml'))) {
            $this->exitWithError('Missing avi.yml');
        }
    }

    protected function configValueByStage($key, $default = null): null|string|array
    {
        return Arr::get($this->config, $this->stage . '.' . $key, $default);
    }

    protected function parseStage()
    {
        $this->stage = Arr::get($this->command->arguments(), 'environment');
    }

    protected function ensureEnvFileExist(): static
    {
        if (!File::exists($this->getLocalEnvFile())) {
            $this->exitWithError("missing .env.{$this->stage} file");
        }
        return $this;
    }

    protected function getLocalEnvFile(): string
    {
        return Path::currentDirectory(".env.{$this->stage}");
    }
}
