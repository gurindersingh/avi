<?php

namespace App\Commands;

use App\Support\Blade;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\File;
use LaravelZero\Framework\Commands\Command;
use React\ChildProcess\Process;
use React\EventLoop\Loop;
use React\Promise\Deferred;
use React\Stream\ReadableStreamInterface;
use function React\Promise\all;
use function Termwind\{render, terminal};

use React\EventLoop\Factory as EventLoopFactory;

class Deploy extends Command
{
    /**
     * The signature of the command.
     *
     * @var string
     */
    protected $signature = 'deploy:web {environment?}';

    /**
     * The description of the command.
     *
     * @var string
     */
    protected $description = 'Deploy code to remote servers';

    protected int $releaseNumber;

    protected string $shellFile;
    protected string $runFile;
    protected string $envFile;
    protected string $sshKey;

    protected array $outputs = [];
    protected array $errors = [];
    protected $startedAt;

    protected array $config = [
        'appName' => null,
        'gitRepo' => null
    ];
    protected ?string $environment = null;

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $this->readConfig();

        $this->ensureEnvExist();

        $this->start()
            ->then(function ($number) {
                $this->startedAt = microtime(true);
                $this->info("Processing...");

                $promises = [];

                $servers = $this->config[$this->environment]['webServers']['ips'];

                foreach ($servers as $server) {
                    $promises[] = $this->copyToServer($server);
                }

                all($promises)
                    ->then(function ($data) {
                        $this->info("Time: " . microtime(true) - $this->startedAt);
                    })
                    ->done(fn() => $this->info('Done'));
            });
    }

    protected function copyToServer($ip): \React\Promise\PromiseInterface|\React\Promise\Promise
    {
        $startedAt = microtime(true);

        $commands = $this->makeCommands($ip);

        $deferred = new Deferred();

        $buffers = [
            'stderr' => '',
            'stdout' => '',
        ];

        $loop = Loop::get();

        $process = new Process($commands, $this->currentDirectory());

        $process->start($loop);

        $process->stdout->on('close', function () use ($loop, $deferred, &$startedAt) {
            $loop->stop();
            $deferred->resolve(microtime(true) - $startedAt);
        });

        $process->on('exit', function ($exitCode, $termSignal) use ($loop, &$deferred, &$startedAt) {
            $loop->stop();
            $deferred->resolve(microtime(true) - $startedAt);
        });

        $process->stdout->on(
            'data',
            function ($output) use (&$stdOut) {
                echo $output . PHP_EOL;
            }
        );

        $process->stderr->on(
            'data',
            function ($output) use (&$stdOut) {
                echo $output . PHP_EOL;
            }
        );

        $loop->run();

        return $deferred->promise();
    }

    protected function homeDir($append = null): string
    {
        $path = $_SERVER['HOME'] ?? $_SERVER['USERPROFILE'];

        return $append ? "{$path}/{$append}" : $path;
    }

    public function currentDirectory($append = null): string
    {
        return $append ? getcwd() . '/' . $append : getcwd();
    }

    protected function start(): \React\Promise\PromiseInterface|\React\Promise\Promise
    {
        $deferred = new Deferred();

        $this->releaseNumber = now()->getTimestamp();

        $this->envFile = $this->currentDirectory('.env.production');

        if (!File::exists($this->envFile)) {
            $this->exitWithError(".env.production file doesn't exits");
        }

        $data = [
            'appName'                  => $this->config['appName'],
            'gitRepo'                  => $this->config['gitRepo'],
            'currentRelease'           => $this->releaseNumber,
            'id_github_apsonex'        => File::get($this->homeDir('.ssh/id_github_apsonex')),
            'id_github_apsonex_public' => File::get($this->homeDir('.ssh/id_github_apsonex.pub')),
            'backup_count'             => 3,
            'composerGithubToken'      => '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',
        ];

        $shellCompiled = Blade::make()->compile(__DIR__ . '/../../stubs/web-server.sh', $data);

        $runCompiled = Blade::make()->compile(__DIR__ . '/../../stubs/run.sh', $data);

        $this->sshKey = $this->homeDir('.ssh/id_gurinder');

        $this->shellFile = realpath(__DIR__ . '/../../stubs/cache') . "/{$this->releaseNumber}.sh";

        $this->runFile = realpath(__DIR__ . '/../../stubs/cache') . "/run.sh";

        File::put($this->shellFile, $shellCompiled);

        File::put($this->runFile, $runCompiled);

        render(
            "<div class=\"pr-1 bg-blue-300 text-black\">
                <span class=\"bg-blue-100 pr-1\">New release number is:</span>
                <span class=\"pl-1 font-bold\">{$this->releaseNumber}</span>
             </div>"
        );

        $deferred->resolve($this->releaseNumber);

        return $deferred->promise();

    }

    protected function markDone()
    {
        $this->info('Done');
    }

    protected function makeCommands($ip): string
    {
        $commands = collect([
            // make deployment folder && copy .env file over
            ['ssh', '-i', $this->sshKey, 'ubuntu@' . $ip, "mkdir -p /home/ubuntu/{$this->config['appName']}/deployments/{$this->releaseNumber}"],
            ['scp', '-rp', '-i', $this->sshKey, $this->envFile, "ubuntu@{$ip}:/home/ubuntu/{$this->config['appName']}/deployments/{$this->releaseNumber}/.env"],
            ['scp', '-rp', '-i', $this->sshKey, $this->shellFile, "ubuntu@{$ip}:/home/ubuntu/{$this->config['appName']}/deployments/{$this->releaseNumber}"],
            ['scp', '-rp', '-i', $this->sshKey, $this->runFile, "ubuntu@{$ip}:/home/ubuntu/{$this->config['appName']}/deployments/{$this->releaseNumber}"],
            ['ssh', '-i', $this->sshKey, 'ubuntu@' . $ip, "zsh /home/ubuntu/{$this->config['appName']}/deployments/{$this->releaseNumber}/run.sh"],

            //['ssh', '-i', $this->sshKey, 'ubuntu@' . $ip, "mkdir -p $currentReleaseDir"],
            //['scp', '-rp', '-i', $this->sshKey, $srcZip, 'ubuntu@' . $ip . ':' . $currentReleaseDir],
            //['ssh', '-i', $this->sshKey, 'ubuntu@' . $ip, "cd $currentReleaseDir && unzip ./code.zip -d $currentReleaseDir"],
        ]);

        return $commands->map(fn($command) => implode(' ', $command))->implode(' && ');
    }

    protected function readConfig()
    {
        if (!File::exists($this->currentDirectory('avi.json'))) {
            $this->exitWithError('avi.json not found. Please run avi deploy:init');
        }

        $this->config = json_decode(File::get($this->currentDirectory('avi.json')), true);

        if (!Arr::get($this->config, 'gitRepo')) {
            $this->exitWithError('Git repo is not defined in avi.json');
        }
    }

    protected function ensureEnvExist()
    {
        $this->environment = Arr::get($this->arguments(), 'environment');

        if ($this->environment) {
            if (!isset($this->config[$this->environment])) {
                $this->exitWithError("{$this->environment} configuration is missing in avi.json file");
            }
        } else {
            $this->askEnvironment();
        }
    }

    protected function askEnvironment(): void
    {
        $this->environment = $this->choice('Environment?', ['development', 'production']);

        if (!$this->environment) {
            $this->askEnvironment();
        }
        
        if (!isset($this->config[$this->environment])) {
            $this->exitWithError("{$this->environment} configuration is missing in avi.json file");
        }
    }

    protected function exitWithError($error): void
    {
        $this->error($error);
        exit(0);
    }
}
