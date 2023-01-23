<?php

namespace App\Support\Deployment;

use App\Support\Path;
use Spatie\Fork\Fork;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;
use LaravelZero\Framework\Commands\Command;
use function Termwind\{render, terminal};

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

        $self->clean();

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

        $config = json_decode(File::get(Path::currentDirectory('avi.json')), true);

        if(json_last_error() > 0) {
            $this->command->error('Invalid avi.json file. ' . json_last_error_msg());
            exit(1);
        }

        $this->config = $config;

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
            'gitRepoAbsoluteAddress'     => str($this->config['gitRepo'])->afterLast(':')->toString(),
            'gitBranch'                   => $this->config[$this->stage]['gitBranch'],
            'currentRelease'              => $this->currentRelease,
            'backupCount'                 => $this->config[$this->stage]['backupCount'],
            'sshKeyPathToConnectToServer' => Arr::get($this->config[$this->stage], 'sshKeyPathToConnectToServer'),
            'gitDeploySshKey'             => Arr::get($this->config[$this->stage], 'gitDeploySshKey'),
            'gitDeploySshKeyContent'      => Arr::has($this->config[$this->stage], 'gitDeploySshKey') ? File::get(Arr::get($this->config[$this->stage], 'gitDeploySshKey')) : null,
            'githubToken'                 => $this->config[$this->stage]['githubToken'] ?? null,
            'composerAuthToken'                 => $this->config[$this->stage]['composerAuthToken'] ?? null,
            'compileVite'                 => $this->config[$this->stage]['compileVite'],
            'compileViteSsr'              => $this->config[$this->stage]['compileViteSsr'] ?? false,
            'composerPostInstallScripts'  => implode("\n", $this->config[$this->stage]['composerPostInstallScripts'] ?? []),
            'postReleaseScripts'          => implode("\n", $this->config[$this->stage]['postReleaseScripts'] ?? []),
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

        $closures = collect(Arr::get($this->config[$this->stage], 'webServers.ips'))->map(function ($ip) {
            return fn() => $this->createCopyScriptToRemoteServerPromise($ip);
        })->toArray();

        $startedAt = microtime(true);

        Fork::new()
            ->after(
                parent: fn() => $this->deploymentFinished()
            )
            ->run(...$closures);

        $this->command->comment('Done in secs: ' . microtime(true) - $startedAt);

        return $this;
    }

    public function createCopyScriptToRemoteServerPromise($ip): string
    {
        $sshFile = $this->config[$this->stage]['sshKeyPathToConnectToServer'];
        $user = $this->config[$this->stage]['user'] ?? 'ubuntu';
        $deployPath = $this->config[$this->stage]['deploy_path'] ?? '/home/ubuntu';

        $commands = collect([
            // make deployment folder && copy .env file over
            ['ssh', '-i', $sshFile, $user . '@' . $ip, "mkdir -p /{$deployPath}/{$this->config['appName']}/deployments/{$this->currentRelease}"],
            ['scp', '-rp', '-i', $sshFile, $this->getLocalEnvFile(), $user . "@{$ip}:{$deployPath}/{$this->config['appName']}/deployments/{$this->currentRelease}/.env"],
            ['scp', '-rp', '-i', $sshFile, $this->bladeCompiler->getLocalDeploymentFilePath(), $user . "@{$ip}:{$deployPath}/{$this->config['appName']}/deployments/{$this->currentRelease}"],
            ['scp', '-rp', '-i', $sshFile, $this->bladeCompiler->getLocalDeploymentFileRunPath(), $user . "@{$ip}:{$deployPath}/{$this->config['appName']}/deployments/{$this->currentRelease}"],
            ['ssh', '-i', $sshFile, $user . '@' . $ip, "zsh {$deployPath}/{$this->config['appName']}/deployments/{$this->currentRelease}/deployment-run.sh"],
        ]);

        $commands = $commands->map(fn($command) => implode(' ', $command))->implode(' && ');

        $process = Process::fromShellCommandline($commands, Path::currentDirectory());

        $process->setTimeout(4000);

        $process->start(function ($type, $buffer) use ($ip) {
            if (Process::ERR === $type) {
                $this->command->error($ip . ':ERR: ' . $buffer);
            } else {
                $this->command->info($ip . ':OUT: ' . $buffer);
            }
        });

        $process->wait();

        return $process->getOutput();
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
        $this->bladeCompiler->removeDir();
    }

    protected function clean(): void
    {
        File::deleteDirectory(Path::currentDirectory('.avi'));
    }
}
