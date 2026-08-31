<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\TerrenoController;
use App\Http\Controllers\ParcelaController;
use App\Http\Controllers\CulturaController;
use App\Http\Controllers\OperacaoController;
use App\Http\Controllers\MaquinaController;
use App\Http\Controllers\AlfaiaController;
use App\Http\Controllers\Api\V1\AplicacaoController;
use App\Http\Controllers\Api\V1\CatalogoController;
use App\Http\Controllers\Api\V1\ColheitaController;
use App\Http\Controllers\Api\V1\CompromissoController;
use App\Http\Controllers\Api\V1\CustoController;
use App\Http\Controllers\Api\V1\FaturaController;
use App\Http\Controllers\Api\V1\PingController;
use App\Http\Controllers\Api\V1\ReceitaController;
use App\Http\Controllers\Api\V1\TesourariaController;
use App\Http\Controllers\Api\V1\TrabalhoController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Toda a API v1 exige um token Sanctum. As leituras precisam so de token
| valido; as escritas exigem tambem role de escrita (api.write.role) e, nos
| endpoints de ingestao, a ability correspondente.
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::middleware(['auth:sanctum', 'throttle:api'])->prefix('v1')->group(function () {

    Route::get('ping', PingController::class);

    /*
    |----------------------------------------------------------------------
    | Cadastro - leitura
    |----------------------------------------------------------------------
    */
    Route::apiResource('terrenos', TerrenoController::class)->only(['index', 'show']);
    Route::apiResource('parcelas', ParcelaController::class)->only(['index', 'show']);
    Route::apiResource('culturas', CulturaController::class)->only(['index', 'show']);
    Route::apiResource('operacoes', OperacaoController::class)->only(['index', 'show']);
    Route::apiResource('maquinas', MaquinaController::class)->only(['index', 'show']);
    Route::apiResource('alfaias', AlfaiaController::class)->only(['index', 'show']);

    Route::get('operacoes-tipos', [OperacaoController::class, 'tipos']);
    Route::get('maquinas-tipos', [MaquinaController::class, 'tipos']);
    Route::get('alfaias-tipos', [AlfaiaController::class, 'tipos']);

    Route::get('campanhas', [CatalogoController::class, 'campanhas']);
    Route::get('funcionarios', [CatalogoController::class, 'funcionarios']);
    Route::get('equipas', [CatalogoController::class, 'equipas']);
    Route::get('produtos', [CatalogoController::class, 'produtos']);

    Route::get('compromissos', [CompromissoController::class, 'index']);
    Route::get('tesouraria', TesourariaController::class);

    /*
    |----------------------------------------------------------------------
    | Cadastro - escrita (exige role de escrita)
    |----------------------------------------------------------------------
    */
    Route::middleware('api.write.role')->group(function () {
        Route::apiResource('terrenos', TerrenoController::class)->except(['index', 'show']);
        Route::post('terrenos/{terreno}/restore', [TerrenoController::class, 'restore']);
        Route::apiResource('parcelas', ParcelaController::class)->except(['index', 'show']);
        Route::apiResource('culturas', CulturaController::class)->except(['index', 'show']);
        Route::apiResource('operacoes', OperacaoController::class)->except(['index', 'show']);
        Route::apiResource('maquinas', MaquinaController::class)->except(['index', 'show']);
        Route::apiResource('alfaias', AlfaiaController::class)->except(['index', 'show']);
    });

    /*
    |----------------------------------------------------------------------
    | Ingestao (exige ability + role de escrita)
    |----------------------------------------------------------------------
    */
    Route::post('custos', [CustoController::class, 'store'])
        ->middleware(['abilities:custos:write', 'api.write.role']);

    Route::post('aplicacoes', [AplicacaoController::class, 'store'])
        ->middleware(['abilities:aplicacoes:write', 'api.write.role']);

    Route::post('trabalhos', [TrabalhoController::class, 'store'])
        ->middleware(['ability:trabalhos:write,custos:write', 'api.write.role']);

    Route::post('faturas', [FaturaController::class, 'store'])
        ->middleware(['ability:faturas:write,custos:write', 'api.write.role']);

    Route::post('compromissos', [CompromissoController::class, 'store'])
        ->middleware(['ability:compromissos:write,custos:write', 'api.write.role']);

    Route::post('compromissos/{compromisso}/concluir', [CompromissoController::class, 'concluir'])
        ->middleware(['ability:compromissos:write,custos:write', 'api.write.role']);

    Route::post('colheitas', [ColheitaController::class, 'store'])
        ->middleware(['abilities:colheitas:write', 'api.write.role']);

    Route::post('receitas', [ReceitaController::class, 'store'])
        ->middleware(['abilities:receitas:write', 'api.write.role']);
});
