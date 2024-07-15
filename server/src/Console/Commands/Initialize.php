<?php

namespace Fleetbase\RegistryBridge\Console\Commands;

use Fleetbase\RegistryBridge\Providers\RegistryBridgeServiceProvider;
use Illuminate\Console\Command;

class Initialize extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'registry:init';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Runs initialization for registry bridge.';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        RegistryBridgeServiceProvider::bootRegistryAuth(true);

        return 0;
    }
}
