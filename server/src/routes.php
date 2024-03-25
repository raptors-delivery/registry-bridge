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

Route::prefix(config('internals.api.routing.prefix', '~registry'))->middleware(['fleetbase.registry'])->namespace('Fleetbase\RegistryBridge\Http\Controllers')->group(
    function ($router) {
        /*
         * Internal Routes v1
         */
        $router->group(['prefix' => config('internals.api.routing.internal_prefix', 'v1'), 'namespace' => 'Internal\v1'], function ($router) {
            $router->group(['prefix' => 'auth'], function ($router) {
                $router->post('authenticate', 'RegistryAuthController@authenticate');
                $router->post('add-user', 'RegistryAuthController@addUser');
                $router->post('check-access', 'RegistryAuthController@checkAccess');
                $router->post('check-publish', 'RegistryAuthController@checkPublishAllowed');
            });
            $router->fleetbaseRoutes('registry-extensions', function ($router, $controller) {
                $router->post('{id}/submit', $controller('submit'));
                $router->post('approve', $controller('approve'));
                $router->post('reject', $controller('reject'));
                $router->get('download-bundle', $controller('downloadBundle'));
            });
            $router->fleetbaseRoutes('registry-extension-bundles', function ($router, $controller) {
                $router->get('download', $controller('download'));
            });
        });
    }
);
