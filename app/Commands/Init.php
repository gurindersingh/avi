<?php

namespace App\Commands;

use App\Support\Path;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use LaravelZero\Framework\Commands\Command;
use function Termwind\{render, terminal};


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
     */
    protected $description = 'Create avi.json file';

    protected array $config = [];

    protected string $stage;

    protected array $availableSshKeys = [];

    /**
     * Execute the console command.
     */
    public function handle()
    {
        terminal()->clear();

        $this->parseConfigFile();

        $this->askGlobalSettings();

        $this->askStageSpecificConfig();

        $this->writeConfigToFile();
    }

    protected function askGlobalSettings()
    {
        $this->askAppName();

        $this->askPhpVersion();

        $this->askGitRepo();
    }

    protected function askAppName()
    {
        terminal()->clear();
        render("<p class='bg-white text-green-700 p-2'>Name your application</p>");
        if (!$this->config['appName'] = $this->ask('App name? ', $this->config['appName'] ?? null)) {
            $this->askAppName();
        }
    }

    protected function askPhpVersion()
    {
        terminal()->clear();
        render("<p class='bg-white text-green-700 p-2'>Select PHP version to use.</p>");
        if (!$this->config['phpVersion'] = $this->choice('Select PHP version?', ['7.4', '8.0', '8.1', '8.2'], $this->config['phpVersion'] ?? null)) {
            $this->askPhpVersion();
        }
    }

    protected function askGitRepo()
    {
        terminal()->clear();
        render("<p class='bg-white text-green-700 p-2'>Add Git repo to use</p>");
        if (!$this->config['gitRepo'] = $this->ask('Git Repo e.g. git@github.com:organization/name.git ?', $this->config['gitRepo'] ?? null)) {
            $this->askGitRepo();
        }
    }

    protected function askStageSpecificConfig()
    {
        $this->askStage();

        $this->askGitBranch();

        $this->askSshKeyToConnectToServer();

        $this->askGithubDeployKey();

        $this->askWebserverIp();

        $this->askGithubToken();

        $this->askBackupCount();

        $this->askToRunViteBuild();

        $this->askComposerPostInstallScripts();

        $this->askPostReleaseScripts();
    }

    protected function askGitBranch()
    {
        terminal()->clear();
        render("<p class='bg-white text-green-700 p-2'>Git branch to deploy</p>");
        if (!$this->config[$this->stage]['gitBranch'] = $this->ask('Git branch to deploy ? ', $this->config[$this->stage]['gitBranch'] ?? null)) {
            $this->askGitBranch();
        }
    }

    protected function askSshKeyToConnectToServer()
    {
        $availableSshKeys = [];

        foreach (File::allFiles(Path::homeDir('.ssh')) as $file) {
            if (Str::startsWith($file->getBasename(), 'id_') && !Str::endsWith($file->getBasename(), '.pub')) {
                $availableSshKeys[] = $file->getPathname();
            }
        }

        terminal()->clear();
        render("<p class='bg-white text-green-700 p-2'>Provide ssh key to connect to remote server and to download repositories from github</p>");

        if (!$this->config[$this->stage]['sshKeyPathToConnectToServer'] = $this->choice('SSH key local path to connect to server. e.g. /home/user/.ssh/id_rsa', $availableSshKeys, Arr::get($this->config, "{$this->stage}.sshKeyPathToConnectToServer"))) {
            $this->askSshKeyToConnectToServer();
        }
    }

    protected function askGithubDeployKey()
    {
        $availableSshKeys = [];

        foreach (File::allFiles(Path::homeDir('.ssh')) as $file) {
            if (Str::startsWith($file->getBasename(), 'id_') && !Str::endsWith($file->getBasename(), '.pub')) {
                $availableSshKeys[] = $file->getPathname();
            }
        }

        terminal()->clear();
        render("<p class='bg-white text-green-700 p-2'>Provide ssh key to connect to remote server and to download repositories from github</p>");

        // if (!$this->config[$this->stage]['gitDeploySshKey'] = $this->choice('SSH Key path - Github deployment ssk key. e.g. /home/user/.ssh/id_rsa', $availableSshKeys, Arr::get($this->config, "{$this->stage}.gitDeploySshKey"))) {
        //     $this->askGithubDeployKey();
        // }

        $gitDeploySshKey = $this->choice(
            'SSH Key path - Github deployment ssk key. e.g. /home/user/.ssh/id_rsa', 
            null, 
            Arr::get($this->config, "{$this->stage}.gitDeploySshKey")
        );
        
        if($gitDeploySshKey) {
            $this->config[$this->stage]['gitDeploySshKey'] = $gitDeploySshKey;
        }
    }

    protected function askGithubToken()
    {
        terminal()->clear();
        render("<p class='bg-white text-green-700 p-2'>Provide Github token for private repositories. Required to download repositories</p>");

        $this->config[$this->stage]['githubToken'] = $this->ask('Github token to pull repository?');

//        if (!$this->config[$this->stage]['githubToken'] = $this->ask('Github token to pull repository?')) {
        //$this->askGithubToken(true);
//        }
    }

    protected function askBackupCount()
    {
        terminal()->clear();
        render("<p class='bg-white text-green-700 p-2'>How many backup to keep on remote sever?</p>");

        $this->config[$this->stage]['backupCount'] = $this->ask('Backup count?', 3);
    }

    protected function askToRunViteBuild()
    {
        terminal()->clear();
        render("<p class='bg-white text-green-700 p-2'>Run vite build</p>");

        $choice = $this->choice('Select vite command to run?', ['Vite', 'Vite with SSR', 'None']);

        if ($choice === 'Vite') {
            $this->config[$this->stage]['compileVite'] = true;
        }

        if ($choice === 'Vite with SSR') {
            $this->config[$this->stage]['compileVite'] = true;
            $this->config[$this->stage]['compileViteSsr'] = true;
        }
    }

    protected function askWebserverIp(\Closure $callback = null)
    {
        terminal()->clear();
        render("<p class='bg-white text-green-700 p-2'>Add IP address of the remote server</p>");

        if ($callback) {
            terminal()->clear();
            $callback();
        }

        $ip = $this->ask("Web Server IP address?");

        if ($ip && !filter_var($ip, FILTER_VALIDATE_IP)) {
            $this->askWebserverIp(function () {
                render("<p class='bg-red-500 text-white p-2'>Provide valid IP address in valid format e.g. 123.123.123.123</p>");
            });
        } else if ($ip && !collect(Arr::get($this->config[$this->stage], 'webServers.ips'))->contains($ip)) {
            $this->config[$this->stage]['webServers']['ips'][] = $ip;
        }
    }

    protected function askStage()
    {
        terminal()->clear();
        render("<p class='bg-white text-green-700 p-2'>Add stage to deploy. Usual stages are development, staging, or production</p>");

        $this->stage = $this->choice("Choose stage?", ['production', 'development', 'staging']);

        if (!$this->stage = Str::camel($this->stage)) {
            $this->askStage();
        };
    }

    protected function parseConfigFile()
    {
        $file = $this->getConfigFilePath();

        $this->config = File::isFile($file) ? json_decode(File::get($file), true) : [];
    }

    protected function writeConfigToFile()
    {
        File::put($this->getConfigFilePath(), json_encode($this->config));
        terminal()->clear();
        render("<p class='bg-white text-green-700 p-2'>Config file written at: <span class='font-bold'>{$this->getConfigFilePath()}</span></p>");
    }

    protected function getConfigFilePath(): string
    {
        return getcwd() . '/avi.json';
    }

    protected function askComposerPostInstallScripts()
    {
        $this->config[$this->stage]['composerPostInstallScripts'] = Arr::get($this->config, "{$this->stage}.composerPostInstallScripts", [
            'php artisan optimize:clear'
        ]);

        terminal()->clear();
        render("<p class='bg-white text-green-700 p-2'>Add Composer post install scripts. e.g. php artisan optimize:clear</p>");

        $scripts = $this->ask('Add comma seperated list of scripts you want to run after composer install?');

        if ($scripts) {
            $this->config[$this->stage]['composerPostInstallScripts'] = collect(explode(',', $scripts))->filter()->map(fn($str) => trim($str))->toArray();
        }
    }
    
    protected function askPostReleaseScripts()
    {
        $this->config[$this->stage]['postReleaseScripts'] = Arr::get($this->config, "{$this->stage}.postReleaseScripts", [
            'php artisan optimize'
        ]);

        terminal()->clear();
        render("<p class='bg-white text-green-700 p-2'>Add post release scripts. e.g. php artisan optimize</p>");

        $scripts = $this->ask('Add comma seperated list of scripts you want to run after new release?');

        if ($scripts) {
            $this->config[$this->stage]['postReleaseScripts'] = collect(explode(',', $scripts))->filter()->map(fn($str) => trim($str))->toArray();
        }
    }
}
