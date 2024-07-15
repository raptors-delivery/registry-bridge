<?php

namespace Fleetbase\RegistryBridge\Console\Commands;

use Fleetbase\RegistryBridge\Models\RegistryExtension;
use Illuminate\Console\Command;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

class PostInstallExtension extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'registry:post-install {extensionId}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Post install an extension by running necessary commands';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $extensionId = $this->argument('extensionId');
        $extension   = RegistryExtension::disableCache()->where('public_id', $extensionId)->first();

        if ($extension) {
            $this->postInstallExtension($extension);
            $this->info('Post install commands executed successfully.');
        } else {
            $this->error('Extension not found.');
        }

        return 0;
    }

    /**
     * Post install extension commands.
     */
    public function postInstallExtension(RegistryExtension $extension): void
    {
        if (isset($extension->currentBundle)) {
            $composerJson = $extension->currentBundle->meta['composer.json'];
            if ($composerJson) {
                $extensionPath = base_path('vendor/' . $composerJson['name']);

                $commands = [
                    'rm -rf /fleetbase/.pnpm-store',
                    'rm -rf node_modules',
                    'pnpm install',
                    'pnpm build',
                ];

                $this->info('Running post install for: ' . $extension->name);
                $this->info('Extension install path: ' . $extensionPath);
                foreach ($commands as $command) {
                    $this->info('Running extension post install command: `' . $command . '`');
                    $process = Process::fromShellCommandline($command, $extensionPath);
                    $process->run(function ($type, $buffer) {
                        if (Process::ERR === $type) {
                            $this->error($buffer);
                        } else {
                            $this->info($buffer);
                        }
                    });

                    // Check if the process was successful
                    if (!$process->isSuccessful()) {
                        throw new ProcessFailedException($process);
                    }
                }
            }
        }
    }
}
