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

class TestConnection extends Command
{
    /**
     * The signature of the command.
     *
     * @var string
     */
    protected $signature = 'deploy:test-connection {environment?}';

    /**
     * The description of the command.
     *
     * @var string
     */
    protected $description = 'Test connections';


    /**
     * Execute the console command.
     */
    public function handle(): void
    {

    }
}
