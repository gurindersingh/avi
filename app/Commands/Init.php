<?php

namespace App\Commands;

use App\Support\Blade;
use Illuminate\Support\Facades\File;
use LaravelZero\Framework\Commands\Command;
use React\ChildProcess\Process;
use React\EventLoop\Loop;
use React\Promise\Deferred;
use React\Stream\ReadableStreamInterface;
use function React\Promise\all;
use function Termwind\{render, terminal};

use React\EventLoop\Factory as EventLoopFactory;

class Init extends Command
{
    /**
     * The signature of the command.
     *
     * @var string
     */
    protected $signature = 'deploy:init';

    /**
     * The description of the command.
     *
     * @var string
     */
    protected $description = 'Create avi.json file';

    protected array $config = [];

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->parseConfigFile();

        $this->askAppName();

        $stage = $this->choice("Choose stage?", ['Production', 'Development']);

        if ($stage === 'Development') {
            $this->config['development']['webServers']['ips'][] = $this->ask("Web Server IP address ?");
        }

        if ($stage === 'Production') {
            $this->config['production']['webServers']['ips'][] = $this->ask("Web Server IP address ?");
        }

        $this->writeConfigToFile();
    }

    protected function parseConfigFile()
    {
        $file = $this->getConfigFilePath();

        $this->config = File::isFile($file) ? json_decode(File::get($file), true) : [];
    }

    protected function writeConfigToFile()
    {
        File::put($this->getConfigFilePath(), json_encode($this->config));
    }

    protected function getConfigFilePath(): string
    {
        return getcwd() . '/avi.json';
    }

    protected function askAppName()
    {
        $this->config['appName'] = $this->ask('App name? ');

        if ($this->config['appName']) return;

        $this->askAppName();
    }

    protected function askGitRepo()
    {
        $this->config['gitRepo'] = $this->ask('Git Repo e.g. git@github.com:organization/name.git ? ');

        if ($this->config['gitRepo']) return;

        $this->askAppName();
    }
}
