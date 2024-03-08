<?php

namespace App\Commands;

use App\Support\Deployment\Deployment;
use function Termwind\{render, terminal};
use LaravelZero\Framework\Commands\Command;

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
