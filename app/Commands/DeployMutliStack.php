<?php

namespace App\Commands;

use App\Support\Path;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\File;
use App\Support\Deployment\Deployment;
use App\Support\Deployment\DeploymentDto;
use function Termwind\{render, terminal};
use LaravelZero\Framework\Commands\Command;
use App\Support\Deployment\MultiStackArtifacts;
use Illuminate\Process\Pool;
use Illuminate\Support\Facades\Process;

class DeployMutliStack extends Command
{
    /**
     * The signature of the command.
     *
     * @var string
     */
    protected $signature = 'deploy:multi-stack {environment?}';

    /**
     * The description of the command.
     *
     * @var string
     */
    protected $description = 'Deploy code to multi-servers';

    protected ?string $stage = null;

    protected array $stacks = [];

    protected DeploymentDto $dto;

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $this->clean();

        $this->dto = DeploymentDto::make()->command($this)->readConfig();

        $stacks = [];

        foreach ($this->dto->queueAndTaskServers['ips'] ?? [] as $ip) {
            $stacks[] = [
                'type' => 'queueAndTask',
                ...MultiStackArtifacts::make()
                    ->queueAndTaskServer()
                    ->ip($ip)
                    ->dto($this->dto)
                    ->preReleaseScripts(
                        Arr::get($this->dto->uniqueCommandsPerIp[$ip] ?? [], 'scripts.preReleaseScripts')
                    )
                    ->postReleaseScripts(
                        Arr::get($this->dto->uniqueCommandsPerIp[$ip] ?? [], 'scripts.postReleaseScripts')
                    )
                    ->makeForServerTypes()
                    ->info()
            ];
        }

        foreach ($this->dto->webServers['ips'] ?? [] as $ip) {
            $stacks[] = [
                'type' => 'web',
                ...MultiStackArtifacts::make()
                    ->web()
                    ->ip($ip)
                    ->dto($this->dto)
                    ->preReleaseScripts(
                        Arr::get($this->dto->uniqueCommandsPerIp[$ip] ?? [], 'scripts.preReleaseScripts')
                    )
                    ->postReleaseScripts(
                        Arr::get($this->dto->uniqueCommandsPerIp[$ip] ?? [], '.scripts.postReleaseScripts')
                    )
                    ->makeForServerTypes()
                    ->info()
            ];
        }

        $this->deployStacks($stacks);

        $this->clean();

        return Command::SUCCESS;
    }

    protected function deployStacks($stacks)
    {
        $this->stacks = $stacks;
        $sshFile = $this->dto->sshKeyPath;
        $user = 'ubuntu';
        $deployPath = '/home/ubuntu';
        $domain = $this->dto->domain;
        $release = $this->dto->newRelease;

        $pool = Process::pool(function (Pool $pool) use (&$stacks, $sshFile, $user, $deployPath, $domain, $release) {
            foreach ($stacks as $i => $stack) {
                $ip = $stack['ip'];

                $remoteAbsoluteReleasePath = $deployPath . '/' . $domain . '/deployments/' . $release;


                $this->stacks['processes'][$i] = [
                    'ip' => $stack['ip'],
                    'file' => '.avi/' . str($stack['directory'])->after('.avi/')->toString(),
                ];

                $runCommand = implode(' ', [
                    'ssh -i ' . $sshFile . ' ' . $user . '@' . $ip,
                    '"cd ' . $remoteAbsoluteReleasePath . ' && zsh ' . $remoteAbsoluteReleasePath . '/run.sh"'
                ]);

                $command = implode(' && ', [
                    'ssh -i ' . $sshFile . ' ' . $user . '@' . $ip . ' "mkdir -p ' . $remoteAbsoluteReleasePath . '"',
                    'rsync -Pavrh -e "ssh -i ' . $sshFile . '" ' . $stack['directory'] . '/* ubuntu@' . $ip . ':' . $remoteAbsoluteReleasePath,
                    $runCommand
                ]);

                $pool
                    ->path(Path::currentDirectory())
                    ->command($command);
            }
        })
            ->start(function (string $type, string $output, int $key) use ($stacks) {
                $ip = $this->stacks['processes'][$key]['ip'];
                $file = $this->stacks['processes'][$key]['file'];

                if ($type === 'err') {
                    $this->error('ERROR @ ' . $ip . ' : ' . $file);
                    echo $output;
                } else {
                    $this->info('OUT');
                    echo $ip . ': ' . $output . PHP_EOL;
                    echo PHP_EOL;
                }
            });

        $processes = invade($pool)->invokedProcesses;

        while ($pool->running()->isNotEmpty()) {

            // foreach ($processes as $process) {
            //     echo $process->latestOutput();
            // }
        }

        $results = $pool->wait();
    }

    protected function uploadStack($stack)
    {
        $sshFile = $this->dto->sshKeyPath;
        $user = 'ubuntu';
        $deployPath = '/home/ubuntu';

        Process::path(Path::currentDirectory())->run(['ssh', '-i', $sshFile, $user . '@' . $stack['ip'], "mkdir -p /{$deployPath}/" . $this->dto->domain . "/deployments/" . $this->dto->newRelease]);
    }

    protected function clean()
    {
        File::deleteDirectory(Path::currentDirectory('.avi'));
    }
}
