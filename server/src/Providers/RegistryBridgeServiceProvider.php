<?php

namespace Fleetbase\RegistryBridge\Providers;

use Fleetbase\Providers\CoreServiceProvider;

if (!class_exists(CoreServiceProvider::class)) {
    throw new \Exception('Registry Bridge cannot be loaded without `fleetbase/core-api` installed!');
}

/**
 * Registry Bridge service provider.
 */
class RegistryBridgeServiceProvider extends CoreServiceProvider
{
    /**
     * The observers registered with the service provider.
     *
     * @var array
     */
    public $observers = [];

    /**
     * The console commands registered with the service provider.
     *
     * @var array
     */
    public $commands = [
        \Fleetbase\RegistryBridge\Console\Commands\PostInstallExtension::class,
        \Fleetbase\RegistryBridge\Console\Commands\Initialize::class,
    ];

    /**
     * The middleware groups registered with the service provider.
     *
     * @var array
     */
    public $middleware = [
        'fleetbase.registry' => [
            'throttle:60,1',
            \Illuminate\Session\Middleware\StartSession::class,
            \Fleetbase\Http\Middleware\AuthenticateOnceWithBasicAuth::class,
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
        ],
    ];

    /**
     * Register any application services.
     *
     * Within the register method, you should only bind things into the
     * service container. You should never attempt to register any event
     * listeners, routes, or any other piece of functionality within the
     * register method.
     *
     * More information on this can be found in the Laravel documentation:
     * https://laravel.com/docs/8.x/providers
     *
     * @return void
     */
    public function register()
    {
        $this->app->register(CoreServiceProvider::class);
    }

    /**
     * Bootstrap any package services.
     *
     * @return void
     *
     * @throws \Exception if the `fleetbase/core-api` package is not installed
     */
    public function boot()
    {
        static::bootRegistryAuth();
        $this->registerCommands();
        $this->registerMiddleware();
        $this->registerExpansionsFrom(__DIR__ . '/../Expansions');
        $this->loadRoutesFrom(__DIR__ . '/../routes.php');
        $this->loadMigrationsFrom(__DIR__ . '/../../migrations');
        $this->mergeConfigFrom(__DIR__ . '/../../config/registry-bridge.php', 'registry-bridge');
    }

    /**
     * Initializes and sets up the npm registry authentication configuration.
     *
     * This method constructs the registry authentication string from configuration settings,
     * checks for the existence of an npmrc file in the user's home directory, and creates it
     * with the registry authentication string if it doesn't already exist.
     *
     * The registry configuration and token are pulled from the application's configuration files.
     * It ensures the path to the .npmrc file is correctly formed regardless of trailing slashes
     * in the HOME directory path or the registry host configuration.
     *
     * @param bool $reset - Overwrites existing file, "resetting" the .npmrc
     *
     * @return void
     */
    public static function bootRegistryAuth(bool $reset = false)
    {
        $homeDirectory  = rtrim(getenv('HOME'), '/');
        $authPath       = $homeDirectory . '/.npmrc';
        $authString     = '//' . str_replace(['http://', 'https://'], '', rtrim(config('registry-bridge.registry.host'), '/')) . '/:_authToken="' . config('registry-bridge.registry.token') . '"' . PHP_EOL;
        if (!file_exists($authPath) || $reset === true) {
            file_put_contents($authPath, $authString);
        }

        $consolePath    = rtrim(config('fleetbase.console.path'), '/');
        $registryPath   = $consolePath . '/.npmrc';
        $registryString = implode(PHP_EOL, [
            'registry=https://registry.npmjs.org/',
            '@fleetbase:registry=' . rtrim(config('registry-bridge.registry.host'), '/') . '/',
        ]) . PHP_EOL;
        if (!file_exists($registryPath) || $reset === true) {
            file_put_contents($registryPath, $registryString);
        }
    }
}
