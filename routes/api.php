<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\TerrenoController;
use App\Http\Controllers\ParcelaController;
use App\Http\Controllers\CulturaController;
use App\Http\Controllers\OperacaoController;
use App\Http\Controllers\MaquinaController;
use App\Http\Controllers\AlfaiaController;
use App\Http\Controllers\Api\V1\PingController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

// Routes publiques (sem autenticação)
Route::prefix('v1')->group(function () {

    // Terrenos
    Route::apiResource('terrenos', TerrenoController::class);
    Route::post('terrenos/{terreno}/restore', [TerrenoController::class, 'restore']);

    // Parcelas
    Route::apiResource('parcelas', ParcelaController::class);

    // Culturas
    Route::apiResource('culturas', CulturaController::class);

    // Operações
    Route::apiResource('operacoes', OperacaoController::class);
    Route::get('operacoes-tipos', [OperacaoController::class, 'tipos']);

    // Máquinas
    Route::apiResource('maquinas', MaquinaController::class);
    Route::get('maquinas-tipos', [MaquinaController::class, 'tipos']);

    // Alfaias
    Route::apiResource('alfaias', AlfaiaController::class);
    Route::get('alfaias-tipos', [AlfaiaController::class, 'tipos']);
});

// Routes protegidas (com autenticação - implementar depois)
Route::middleware('auth:sanctum')->prefix('v1')->group(function () {
    Route::get('ping', PingController::class);
});
