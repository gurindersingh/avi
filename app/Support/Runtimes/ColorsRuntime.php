<?php

namespace App\Support\Runtimes;

class ColorsRuntime extends BaseRuntime
{

    protected function process()
    {
        $this->addContent('colors', $this->content());
    }


    protected function content(): string
    {
        $string = <<<'BLADE'

GREEN='\033[0;32m'
echo_green() {
    echo -e "${GREEN}${1}${NOCOLOR}"
}

BLUE='\033[0;34m'
echo_blue() {
    echo -e "${BLUE}${1}${NOCOLOR}"
}

PURPLE='\033[0;35m'
echo_purple() {
    echo -e "${PURPLE}${1}${NOCOLOR}"
}

WHITE='\033[1;37m'
echo_white() {
    echo -e "${WHITE}${1}${NOCOLOR}"
}

BLADE;

        return $this->compileBlade($string, [
            'domain' => $this->dto()->domain,
            'newRelease' => $this->dto()->newRelease,
            'compilerScript' => $this->compilerScript(),
        ]);
    }

    protected function compilerScript(): string
    {
        return match ($this->dto()->jsCompiler) {
            'pnpm' => 'pnpm install && pnpm run build',
            'bun' => 'bun install && bun run build --mode production',
            default => 'npm install && npm run build'
        };
    }
}
