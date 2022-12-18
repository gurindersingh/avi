<?php

namespace App\Support\Deployment;

trait Utilities
{

    protected function exitWithError($error): void
    {
        $this->command->error($error);
        exit(0);
    }

}
