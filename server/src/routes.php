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

Route::prefix(config('internals.api.routing.prefix', '~registry'))->namespace('Fleetbase\RegistryBridge\Http\Controllers\Internal\v1')->group(
    function ($router) {
        $router->group(['namespace' => 'auth'], function ($router) {
            $router->post('add-user', 'RegistryAuthController@addUser');
        });
    }
);
