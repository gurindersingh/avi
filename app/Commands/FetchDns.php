<?php

namespace App\Commands;

use Illuminate\Console\Scheduling\Schedule;
use LaravelZero\Framework\Commands\Command;
use Spatie\Dns\Dns;

class FetchDns extends Command
{
    /**
     * The signature of the command.
     *
     * @var string
     */
    protected $signature = 'dns:show {domain}';

    /**
     * The description of the command.
     *
     * @var string
     */
    protected $description = 'Fetch DNS settings for domain';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle(Dns $dns)
    {
        if(!$this->argument('domain')) {
            $this->error('Provide domain');
            exit(1);
        }

        dd(
            $dns->getRecords($this->argument('domain'))
        );
    }
}
