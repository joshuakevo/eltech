<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ClientController;

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

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();

});
//
// Public routes (no token needed)
Route::post('/auth/login', [AuthController::class, 'login']);

// Protected routes (token required)
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/auth/logout', [AuthController::class, 'logout']);

    // Client portal
    Route::prefix('client')->group(function () {
        Route::get('/profile',      [ClientController::class, 'profile']);
        Route::get('/accounts',     [ClientController::class, 'accounts']);
        Route::get('/transactions', [ClientController::class, 'transactions']);
        Route::get('/loans',        [ClientController::class, 'loans']);
    });
});