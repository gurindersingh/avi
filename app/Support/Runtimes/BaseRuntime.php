<?php

namespace App\Support\Runtimes;

use App\Concerns\Makeable;
use App\Support\Blade;
use App\Support\Deployment\DeploymentDto;

abstract class BaseRuntime
{
    use Makeable;

    protected array $data;

    abstract protected function process();

    public function handle($data, $next)
    {
        $this->data = $data;

        $this->process();

        return $next($this->data);
    }

    public function setDto($dto): static
    {
        $this->data['dto'] = $dto;
        return $this;
    }

    protected function compileBlade($string, $data): string
    {
        return Blade::singleton()->compile($string, $data);
    }

    protected function dto(): DeploymentDto
    {
        return $this->data['dto'];
    }

    protected function addContent($key, $content)
    {
        $this->data['content'][$key] = $content;
    }

    protected function isWeb(): bool
    {
        return $this->data['type'] === 'web';
    }


}
