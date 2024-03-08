<?php

namespace App\Support\Runtimes;

class CleanOldDeploymentsRuntime extends BaseRuntime
{

    protected function process()
    {
        $this->addContent('clean_old_releases', $this->content());
    }


    protected function content(): string
    {
        $string = <<<'BLADE'

# Clean old releases

cd /home/ubuntu/{{ $domain }}/releases
PWD=$(pwd)
for OUTPUT in $(ls -A | sort  | head -n -{{ $backupCount }})
do
    if [ -d "$PWD/$OUTPUT" ]
    then
        echo "Removing release: $OUTPUT "
        rm -rf "$PWD/$OUTPUT"
    else
        echo "Release Directory does not exit: $PWD/$OUTPUT"
    fi
done

cd /home/ubuntu/{{ $domain }}/deployments
PWD=$(pwd)
for OUTPUT in $(ls -A | sort  | head -n -{{ $backupCount }})
do
    if [ -d "$PWD/$OUTPUT" ]
    then
        echo "Removing deployments: $OUTPUT "
        rm -rf "$PWD/$OUTPUT"
    else
        echo "Deployment Directory does not exit: $PWD/$OUTPUT"
    fi
done

BLADE;

        return $this->compileBlade($string, [
            'domain' => $this->dto()->domain,
            'newRelease' => $this->dto()->newRelease,
            'phpVersion' => $this->dto()->php,
            'backupCount' => $this->dto()->backup,
        ]);
    }
}
