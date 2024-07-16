<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::get('~registry/test', 'Fleetbase\RegistryBridge\Http\Controllers\Internal\v1\RegistryAuthController@test');

Route::prefix(config('internals.api.routing.prefix', '~registry'))->middleware(['fleetbase.registry'])->namespace('Fleetbase\RegistryBridge\Http\Controllers')->group(
    function ($router) {
        /*
         * Internal Routes v1
         */
        $router->group(['prefix' => config('internals.api.routing.internal_prefix', 'v1'), 'namespace' => 'Internal\v1'], function ($router) {
            $router->get('categories', 'RegistryController@categories');
            $router->get('load-installed-engines', 'RegistryController@loadInstalledEngines');
            $router->get('load-engine-manifest/{extensionId}', 'RegistryController@loadEngineManifest');
            $router->group(['prefix' => 'installer'], function ($router) {
                $router->post('install', 'ExtensionInstallerController@install');
                $router->post('uninstall', 'ExtensionInstallerController@uninstall');
            });
            $router->group(['prefix' => 'auth'], function ($router) {
                $router->post('authenticate', 'RegistryAuthController@authenticate');
                $router->post('add-user', 'RegistryAuthController@addUser');
                $router->post('check-access', 'RegistryAuthController@checkAccess');
                $router->post('check-publish', 'RegistryAuthController@checkPublishAllowed');
            });
            $router->group(['prefix' => 'payments'], function ($router) {
                $router->post('account', 'RegistryPaymentsController@getStripeAccount');
                $router->post('account-session', 'RegistryPaymentsController@getStripeAccountSession');
                $router->get('has-stripe-connect-account', 'RegistryPaymentsController@hasStripeConnectAccount');
                $router->post('create-checkout-session', 'RegistryPaymentsController@createStripeCheckoutSession');
                $router->post('get-checkout-session', 'RegistryPaymentsController@getStripeCheckoutSessionStatus');
            });
            $router->fleetbaseRoutes('registry-extensions', function ($router, $controller) {
                $router->post('{id}/submit', $controller('submit'));
                $router->post('approve', $controller('approve'));
                $router->post('reject', $controller('reject'));
                $router->get('download-bundle', $controller('downloadBundle'));
                $router->get('installed', $controller('installed'))->middleware([Spatie\ResponseCache\Middlewares\DoNotCacheResponse::class]);
            });
            $router->fleetbaseRoutes('registry-extension-bundles', function ($router, $controller) {
                $router->get('download', $controller('download'));
            });
        });
    }
);
