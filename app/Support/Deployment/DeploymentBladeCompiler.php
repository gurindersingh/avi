<?php

namespace App\Support\Deployment;

use App\Support\Blade;
use App\Support\Path;
use Illuminate\Support\Facades\File;

class DeploymentBladeCompiler
{

    protected string $localDeploymentFilePath;

    protected string $localDeploymentFileRunPath;

    protected string $artifactsRootDir;
    protected string $artifactsDir;

    public static function make(): static
    {
        return new self();
    }

    public function compile(Deployment $deployment): static
    {
        $this->cleanDir();

        config(['view.compiled' => base_path('storage/framework/cache')]);

        $this->artifactsDir = $this->artifactsRootDir . '/' . $deployment->getCurrentRelease();

        File::ensureDirectoryExists($this->artifactsDir);



        $shellCompiled = Blade::make()->compile(
            base_path('stubs/deployment.sh'),
            $deployment->getBladeVars()
        );

        $runCompiled = Blade::make()->compile(
            base_path('stubs/deployment-run.sh'),
            $deployment->getBladeVars()
        );

        $this->localDeploymentFilePath = $this->artifactsDir . '/deployment.sh';
        File::put($this->localDeploymentFilePath, $shellCompiled);

        $this->localDeploymentFileRunPath = $this->artifactsDir . '/deployment-run.sh';
        File::put($this->localDeploymentFileRunPath, $runCompiled);

        return $this;
    }

    public function getLocalDeploymentFilePath(): string
    {
        return $this->localDeploymentFilePath;
    }

    public function getLocalDeploymentFileRunPath(): string
    {
        return $this->localDeploymentFileRunPath;
    }

    protected function cleanDir(): void
    {
        File::deleteDirectory(Path::currentDirectory('.avi'));

        $this->artifactsRootDir = Path::currentDirectory('.avi');
    }

}
