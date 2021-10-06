<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assignerefred the "api" middleware group. Enjoy building your API!
|
*/

//Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
//    return $request->user();
//});

// PUBLIC ROUTES
Route::apiResource('user', 'UserController')->only( 'store')->withoutMiddleware('auth:api');

// PROTECTED ROUTES
Route::middleware('auth:api')->group(function () {
    Route::name('user.')->prefix('user')->group(function () {
        Route::post('login', 'UserController@login')->name('login')->withoutMiddleware('auth:api');
        Route::post('refresh', 'UserController@refreshToken')->name('refresh')->withoutMiddleware('auth:api');
        Route::post('logout', 'UserController@logout')->name('logout');
        Route::post('restore/{user}', 'UserController@restore')->name('restore');
        Route::get('get/self', 'UserController@getSelf')->name('self');
    });
    Route::apiResource('user', 'UserController')->only( 'index', 'show', 'destroy');
});
