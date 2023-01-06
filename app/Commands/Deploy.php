<?php

namespace App\Commands;

use App\Support\Blade;
use App\Support\Deployment\Deployment;
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
    protected ?string $stage = null;

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        Deployment::handle($this);

        return Command::SUCCESS;
    }
}
