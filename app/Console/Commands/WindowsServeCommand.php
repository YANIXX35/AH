<?php

namespace App\Console\Commands;

use Illuminate\Foundation\Console\ServeCommand as BaseServeCommand;
use Illuminate\Support\Collection;
use Symfony\Component\Process\Process;

class WindowsServeCommand extends BaseServeCommand
{
    /**
     * Sur Windows, le mode de filtrage d'environnement utilise par Laravel
     * peut faire echouer le serveur integre PHP. On conserve le comportement
     * Laravel, mais on transmet l'environnement complet.
     */
    protected function startProcess($hasEnvironment)
    {
        $environment = PHP_OS_FAMILY === 'Windows'
            ? (new Collection($_ENV))->all()
            : (new Collection($_ENV))->mapWithKeys(function ($value, $key) use ($hasEnvironment) {
                if ($this->option('no-reload') || ! $hasEnvironment) {
                    return [$key => $value];
                }

                return in_array($key, static::$passthroughVariables, true)
                    ? [$key => $value]
                    : [$key => false];
            })->all();

        $process = new Process(
            $this->serverCommand(),
            public_path(),
            array_merge($environment, ['PHP_CLI_SERVER_WORKERS' => $this->phpServerWorkers])
        );

        $this->trap(fn () => [SIGTERM, SIGINT, SIGHUP, SIGUSR1, SIGUSR2, SIGQUIT], function ($signal) use ($process) {
            if ($process->isRunning()) {
                $process->stop(10, $signal);
            }

            exit;
        });

        $process->start($this->handleProcessOutput());

        return $process;
    }
}
