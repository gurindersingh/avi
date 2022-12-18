<?php

namespace App\Support\Deployment;

use App\Support\Path;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\File;
use LaravelZero\Framework\Commands\Command;
use React\ChildProcess\Process;
use React\EventLoop\Loop;
use React\Promise\Deferred;
use React\Promise\Promise;
use React\Promise\PromiseInterface;
use function Termwind\{render, terminal};
use function React\Promise\all;

class Deployment
{
    use Utilities;

    protected Command $command;

    protected array $config = [];

    protected string $stage;

    protected array $bladeVars = [
        'appName' => null,
        'gitRepo' => null,
        'gitBranch' => null,
        'currentRelease' => null,
        'id_github_apsonex' => null,
        'id_github_apsonex_public' => null,
        'backup_count' => 3,
        'composerGithubToken' => null,
    ];

    protected DeploymentBladeCompiler $bladeCompiler;

    protected int $currentRelease;

    public static function handle(Command $command): Deployment
    {
        $self = new self();
        $self->currentRelease = now()->getTimestamp();
        $self->command = $command;
        $self->readConfig()
            ->ensureConfigExist()
            ->ensureEnvFileExist()
            ->configBladeVars()
            ->compileBladeTemplate()
            ->copyScriptToRemoteServer();
        return $self;
    }

    protected function readConfig(): static
    {
        if (!File::exists(Path::currentDirectory('avi.json'))) {
            $this->exitWithError('avi.json not found. Please run avi deploy:init');
        }

        $this->config = json_decode(File::get(Path::currentDirectory('avi.json')), true);

        if (!Arr::get($this->config, 'gitRepo')) {
            $this->exitWithError('Git repo is not defined in avi.json');
        }

        return $this;
    }

    protected function ensureConfigExist(): static
    {
        if (
            (!$this->stage = Arr::get($this->command->arguments(), 'environment')) &&
            !isset($this->config[$this->stage])
        ) {
            $this->exitWithError("{$this->stage} configuration is missing in avi.json file");
        }

        return $this;
    }

    protected function ensureEnvFileExist(): static
    {
        if (!File::exists($this->getLocalEnvFile())) {
            $this->exitWithError("missing .env.{$this->stage} file");
        }
        return $this;
    }

    protected function configBladeVars(): static
    {
        $this->bladeVars = [
            'appName' => $this->config['appName'],
            'phpVersion' => $this->config['phpVersion'],
            'gitRepo' => $this->config['gitRepo'],
            'gitBranch' => $this->config['gitBranch'],
            'currentRelease' => $this->currentRelease,
            'backupCount' => $this->config[$this->stage]['backupCount'],
            'composerGithubToken' => $this->config[$this->stage]['githubToken'],
            'sshKeyPath' => $this->getSshKeyPath(),
            'sshPrivateKeyContent' => $this->getPrivateSshKeyContent(),
            'githubToken' => $this->config[$this->stage]['githubToken'],
            'compileVite' => $this->config[$this->stage]['compileVite'],
            'compileViteSsr' => $this->config[$this->stage]['compileViteSsr'],
        ];
        return $this;
    }

    protected function compileBladeTemplate(): static
    {
        $this->bladeCompiler = DeploymentBladeCompiler::make()->compile($this);
        return $this;
    }

    protected function copyScriptToRemoteServer(): static
    {
        terminal()->clear();
        render("<p class='bg-white text-green-700 p-2'>Copying depolyment scrips on remote servers</p>");

        $promises = [];

        foreach (Arr::get($this->config[$this->stage], 'webServers.ips') as $ip) {
            $promises[] = $this->createCopyScriptToRemoteServerPromise($ip);
        }

        all($promises)
            ->progress(function ($name) {
                $this->command->info($name);
            })
            ->then(function ($data) {

            })
            ->done(fn() => $this->command->info('Done'));

        return $this;
    }

    protected function createCopyScriptToRemoteServerPromise($ip): PromiseInterface|Promise
    {
        $commands = collect([
            // make deployment folder && copy .env file over
            ['ssh', '-i', $this->getSshKeyPath(), 'ubuntu@' . $ip, "mkdir -p /home/ubuntu/{$this->config['appName']}/deployments/{$this->currentRelease}"],
            ['scp', '-rp', '-i', $this->getSshKeyPath(), $this->getLocalEnvFile(), "ubuntu@{$ip}:/home/ubuntu/{$this->config['appName']}/deployments/{$this->currentRelease}/.env"],
            ['scp', '-rp', '-i', $this->getSshKeyPath(), $this->bladeCompiler->getLocalDeploymentFilePath(), "ubuntu@{$ip}:/home/ubuntu/{$this->config['appName']}/deployments/{$this->currentRelease}"],
            ['scp', '-rp', '-i', $this->getSshKeyPath(), $this->bladeCompiler->getLocalDeploymentFileRunPath(), "ubuntu@{$ip}:/home/ubuntu/{$this->config['appName']}/deployments/{$this->currentRelease}"],
            ['ssh', '-i', $this->getSshKeyPath(), 'ubuntu@' . $ip, "zsh /home/ubuntu/{$this->config['appName']}/deployments/{$this->currentRelease}/deployment-run.sh"],
        ]);

        $commands = $commands->map(fn($command) => implode(' ', $command))->implode(' && ');

        $startedAt = microtime(true);

        $deferred = new Deferred();

        $loop = Loop::get();

        $process = new Process($commands, Path::currentDirectory());

        $process->start($loop);

        $process->stdout->on('close', function () use ($loop, $deferred, &$startedAt) {
            $loop->stop();
            $deferred->resolve(microtime(true) - $startedAt);
        });

        $process->on('exit', function ($exitCode, $termSignal) use ($loop, &$deferred, &$startedAt) {
            $loop->stop();
            $deferred->resolve(microtime(true) - $startedAt);
        });

        $process->stdout->on('data', fn($output) => $this->command->info($ip . ': ' . $output));

        $process->stderr->on('data', fn($output) => $this->command->error($ip . ': ' . $output));

        $loop->run();

        return $deferred->promise();
    }

    public function getBladeVars(): array
    {
        return $this->bladeVars;
    }

    protected function getSshKeyPath(): string
    {
        return Arr::get($this->config[$this->stage], 'sshKeyPath');
    }

    public function getCurrentRelease(): int
    {
        return $this->currentRelease;
    }

    protected function getLocalEnvFile(): string
    {
        return Path::currentDirectory(".env.{$this->stage}");
    }

    protected function getPrivateSshKeyContent(): string
    {
        return File::get($this->getSshKeyPath());
    }

}
