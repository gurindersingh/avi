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
        'appName'                  => null,
        'gitRepo'                  => null,
        'gitBranch'                => null,
        'currentRelease'           => null,
        'id_github_apsonex'        => null,
        'id_github_apsonex_public' => null,
        'backup_count'             => 3,
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
            'appName'                     => $this->config['appName'],
            'phpVersion'                  => $this->config['phpVersion'],
            'gitRepo'                     => $this->config['gitRepo'],
            'gitBranch'                   => $this->config['gitBranch'],
            'currentRelease'              => $this->currentRelease,
            'backupCount'                 => $this->config[$this->stage]['backupCount'],
            'sshKeyPathToConnectToServer' => Arr::get($this->config[$this->stage], 'sshKeyPathToConnectToServer'),
            'gitDeploySshKey'             => Arr::get($this->config[$this->stage], 'gitDeploySshKey'),
            'gitDeploySshKeyContent'      => File::get(Arr::get($this->config[$this->stage], 'gitDeploySshKey')),
            'githubToken'                 => $this->config[$this->stage]['githubToken'] ?? null,
            'compileVite'                 => $this->config[$this->stage]['compileVite'],
            'compileViteSsr'              => $this->config[$this->stage]['compileViteSsr'] ?? false,
            'composerPostInstallScripts'  => implode("\n", $this->config[$this->stage]['composerPostInstallScripts'] ?? []),
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
        render("<p class='bg-white text-green-700 p-2'>Copying deployments scrips on remote servers</p>");

        $promises = [];

        foreach (Arr::get($this->config[$this->stage], 'webServers.ips') as $ip) {
            $promises[] = $this->createCopyScriptToRemoteServerPromise($ip);
        }

        all($promises)
            ->progress(fn($name) => $this->command->info($name))
            ->then(function ($data) {
                //
            })
            ->done(fn() => $this->deploymentFinished());

        return $this;
    }

    protected function createCopyScriptToRemoteServerPromise($ip): PromiseInterface|Promise
    {
        $sshFile = $this->config[$this->stage]['sshKeyPathToConnectToServer'];

        $commands = collect([
            // make deployment folder && copy .env file over
            ['ssh', '-i', $sshFile, 'ubuntu@' . $ip, "mkdir -p /home/ubuntu/{$this->config['appName']}/deployments/{$this->currentRelease}"],
            ['scp', '-rp', '-i', $sshFile, $this->getLocalEnvFile(), "ubuntu@{$ip}:/home/ubuntu/{$this->config['appName']}/deployments/{$this->currentRelease}/.env"],
            ['scp', '-rp', '-i', $sshFile, $this->bladeCompiler->getLocalDeploymentFilePath(), "ubuntu@{$ip}:/home/ubuntu/{$this->config['appName']}/deployments/{$this->currentRelease}"],
            ['scp', '-rp', '-i', $sshFile, $this->bladeCompiler->getLocalDeploymentFileRunPath(), "ubuntu@{$ip}:/home/ubuntu/{$this->config['appName']}/deployments/{$this->currentRelease}"],
            ['ssh', '-i', $sshFile, 'ubuntu@' . $ip, "zsh /home/ubuntu/{$this->config['appName']}/deployments/{$this->currentRelease}/deployment-run.sh"],
        ]);

        $commands = $commands->map(fn($command) => implode(' ', $command))->implode(' && ');

        $startedAt = microtime(true);

        $deferred = new Deferred();

        $loop = Loop::get();

        $process = new Process($commands, Path::currentDirectory());

        $process->start($loop);

        $process->stdout->on('close', function () use ($loop, $deferred, &$startedAt, $ip) {
            $loop->stop();
            $timeTook = microtime(true) - $startedAt;
            $deferred->resolve($timeTook);
            $this->command->info("{$ip} Finished in secs: " . $timeTook);
        });

        $process->on('exit', function ($exitCode, $termSignal) use ($loop, &$deferred, &$startedAt) {
            $loop->stop();
            $timeTook = microtime(true) - $startedAt;
            $deferred->resolve($timeTook);
        });

        $process->stdout->on('data', function ($output) use ($ip, $startedAt) {
            render("<p class='bg-white text-green-700 p-2'>{$ip} Output</p>");
            $this->command->info($output);
        });

        $process->stderr->on('data', function ($output) use ($ip) {
            render("<p class='bg-red text-white p-2'>{$ip} Output</p>");
            $this->command->error($output);
        });

        $loop->run();

        return $deferred->promise();
    }

    public function getBladeVars(): array
    {
        return $this->bladeVars;
    }

    public function getCurrentRelease(): int
    {
        return $this->currentRelease;
    }

    protected function getLocalEnvFile(): string
    {
        return Path::currentDirectory(".env.{$this->stage}");
    }

    protected function deploymentFinished(): void
    {
        $this->command->info('Done');
        $this->bladeCompiler->removeDir();
    }
}
