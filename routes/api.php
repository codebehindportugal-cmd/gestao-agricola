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
use App\Http\Controllers\Api\V1\CadastroEscritaController;
use App\Http\Controllers\Api\V1\CampanhaGestaoController;
use App\Http\Controllers\Api\V1\CasaController;
use App\Http\Controllers\Api\V1\CatalogoController;
use App\Http\Controllers\Api\V1\ColheitaController;
use App\Http\Controllers\Api\V1\CompromissoController;
use App\Http\Controllers\Api\V1\ConsultaController;
use App\Http\Controllers\Api\V1\CorrecaoController;
use App\Http\Controllers\Api\V1\CustoController;
use App\Http\Controllers\Api\V1\FaturaController;
use App\Http\Controllers\Api\V1\ManutencaoController;
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
    | Consultas - as contas da exploracao
    |----------------------------------------------------------------------
    |
    | A API sabia gravar e quase nao sabia contar. Isto e' o que permite
    | responder pelo chat a "quanto custou a apanha das pereiras" ou "esta
    | fatura ja entrou", sem abrir o site. Filtros comuns: de, ate, pagina,
    | por_pagina. As referencias aceitam id ou nome.
    */
    Route::get('resumo', [ConsultaController::class, 'resumo']);
    Route::get('campanhas/{referencia}/resumo', [ConsultaController::class, 'resumo']);
    Route::get('custos', [ConsultaController::class, 'custos']);
    Route::get('colheitas', [ConsultaController::class, 'colheitas']);
    Route::get('receitas', [ConsultaController::class, 'receitas']);
    Route::get('despesas', [ConsultaController::class, 'despesas']);
    Route::get('stock', [ConsultaController::class, 'stock']);
    Route::get('movimentos-stock', [ConsultaController::class, 'movimentosStock']);
    Route::get('manutencoes', [ConsultaController::class, 'manutencoes']);

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

    // Varias faturas de uma vez — a pilha de papel fotografada de seguida.
    // Declarada antes de 'faturas/{despesa}/ficheiro' nao e' preciso (o numero
    // de segmentos e' diferente), mas fica junto do store por ser o mesmo caso.
    Route::post('faturas/lote', [FaturaController::class, 'lote'])
        ->middleware(['ability:faturas:write,custos:write', 'api.write.role']);

    // A foto da fatura, em multipart. Separada do store porque o corpo deste
    // e' o ficheiro e o do store e' JSON com as linhas; juntar os dois obrigava
    // quem envia a codificar a imagem em texto, e uma foto de telemovel nao
    // cabe la. O documento e' preciso para o caderno de campo.
    Route::post('faturas/{despesa}/ficheiro', [FaturaController::class, 'ficheiro'])
        ->middleware(['ability:faturas:write,custos:write', 'api.write.role']);

    Route::post('compromissos', [CompromissoController::class, 'store'])
        ->middleware(['ability:compromissos:write,custos:write', 'api.write.role']);

    Route::post('compromissos/{compromisso}/concluir', [CompromissoController::class, 'concluir'])
        ->middleware(['ability:compromissos:write,custos:write', 'api.write.role']);

    Route::post('colheitas', [ColheitaController::class, 'store'])
        ->middleware(['abilities:colheitas:write', 'api.write.role']);

    Route::post('receitas', [ReceitaController::class, 'store'])
        ->middleware(['abilities:receitas:write', 'api.write.role']);

    /*
    |----------------------------------------------------------------------
    | Correccoes: alterar e apagar o que foi mal registado
    |----------------------------------------------------------------------
    |
    | O POST das faturas e' idempotente pelo numero: reenviar nao corrige nada,
    | e ate aqui a unica saida era ir ao ecra. Tudo o que se apaga por aqui e'
    | soft delete e tem restauro; apagar uma fatura desfaz as entradas em stock
    | e arquiva o custo que ela criou.
    */
    Route::patch('faturas/{despesa}', [CorrecaoController::class, 'atualizarFatura'])
        ->middleware(['ability:faturas:write,custos:write', 'api.write.role']);
    Route::delete('faturas/{despesa}', [CorrecaoController::class, 'apagarFatura'])
        ->middleware(['ability:faturas:write,custos:write', 'api.write.role']);
    Route::post('faturas/{id}/restaurar', [CorrecaoController::class, 'restaurarFatura'])
        ->middleware(['ability:faturas:write,custos:write', 'api.write.role']);

    Route::patch('custos/{custo}', [CorrecaoController::class, 'atualizarCusto'])
        ->middleware(['abilities:custos:write', 'api.write.role']);
    Route::delete('custos/{custo}', [CorrecaoController::class, 'apagarCusto'])
        ->middleware(['abilities:custos:write', 'api.write.role']);
    Route::post('custos/{id}/restaurar', [CorrecaoController::class, 'restaurarCusto'])
        ->middleware(['abilities:custos:write', 'api.write.role']);

    Route::patch('colheitas/{colheita}', [CorrecaoController::class, 'atualizarColheita'])
        ->middleware(['ability:colheitas:write,custos:write', 'api.write.role']);
    Route::delete('colheitas/{colheita}', [CorrecaoController::class, 'apagarColheita'])
        ->middleware(['ability:colheitas:write,custos:write', 'api.write.role']);
    Route::post('colheitas/{id}/restaurar', [CorrecaoController::class, 'restaurarColheita'])
        ->middleware(['ability:colheitas:write,custos:write', 'api.write.role']);

    Route::patch('receitas/{receita}', [CorrecaoController::class, 'atualizarReceita'])
        ->middleware(['ability:receitas:write,custos:write', 'api.write.role']);
    Route::delete('receitas/{receita}', [CorrecaoController::class, 'apagarReceita'])
        ->middleware(['ability:receitas:write,custos:write', 'api.write.role']);
    Route::post('receitas/{id}/restaurar', [CorrecaoController::class, 'restaurarReceita'])
        ->middleware(['ability:receitas:write,custos:write', 'api.write.role']);

    /*
    |----------------------------------------------------------------------
    | Manutencoes: revisoes de maquinas e alfaias
    |----------------------------------------------------------------------
    */
    Route::post('manutencoes', [ManutencaoController::class, 'store'])
        ->middleware(['ability:manutencoes:write,custos:write', 'api.write.role']);
    Route::patch('manutencoes/{manutencao}', [ManutencaoController::class, 'update'])
        ->middleware(['ability:manutencoes:write,custos:write', 'api.write.role']);
    Route::delete('manutencoes/{manutencao}', [ManutencaoController::class, 'destroy'])
        ->middleware(['ability:manutencoes:write,custos:write', 'api.write.role']);
    Route::post('manutencoes/{id}/restaurar', [ManutencaoController::class, 'restore'])
        ->middleware(['ability:manutencoes:write,custos:write', 'api.write.role']);

    /*
    |----------------------------------------------------------------------
    | Campanhas: criar, fechar e unificar
    |----------------------------------------------------------------------
    |
    | 'campanhas/unificar' vem antes de qualquer 'campanhas/{...}' com POST
    | para nao ser apanhada como um id.
    */
    Route::post('campanhas/unificar', [CampanhaGestaoController::class, 'unificar'])
        ->middleware(['ability:campanhas:write,custos:write', 'api.write.role']);
    Route::post('campanhas', [CampanhaGestaoController::class, 'store'])
        ->middleware(['ability:campanhas:write,custos:write', 'api.write.role']);
    Route::patch('campanhas/{campanha}', [CampanhaGestaoController::class, 'update'])
        ->middleware(['ability:campanhas:write,custos:write', 'api.write.role']);

    /*
    |----------------------------------------------------------------------
    | Cadastro: stock, pessoas, fornecedores, armazens, produtos
    |----------------------------------------------------------------------
    */
    Route::post('stock/ajustes', [CadastroEscritaController::class, 'ajustarStock'])
        ->middleware(['ability:stock:write,custos:write', 'api.write.role']);

    Route::middleware(['ability:cadastro:write,custos:write', 'api.write.role'])->group(function () {
        Route::post('funcionarios', [CadastroEscritaController::class, 'guardarFuncionario']);
        Route::patch('funcionarios/{funcionario}', [CadastroEscritaController::class, 'guardarFuncionario']);
        Route::post('equipas', [CadastroEscritaController::class, 'guardarEquipa']);
        Route::patch('equipas/{equipa}', [CadastroEscritaController::class, 'guardarEquipa']);
        Route::post('fornecedores', [CadastroEscritaController::class, 'guardarFornecedor']);
        Route::patch('fornecedores/{fornecedor}', [CadastroEscritaController::class, 'guardarFornecedor']);
        Route::post('armazens', [CadastroEscritaController::class, 'guardarArmazem']);
        Route::patch('armazens/{armazem}', [CadastroEscritaController::class, 'guardarArmazem']);
        Route::post('produtos', [CadastroEscritaController::class, 'guardarProduto']);
        Route::patch('produtos/{produto}', [CadastroEscritaController::class, 'guardarProduto']);
    });

    // Casa (Home Assistant -> site). O sentido e' sempre este: o servidor nunca
    // inicia ligacoes para a rede de casa. O token do HA leva so 'casa:write',
    // por isso nao toca em faturas, custos nem colheitas.
    Route::post('casa/eventos', [CasaController::class, 'evento'])
        ->middleware(['abilities:casa:write', 'api.write.role']);

    Route::post('casa/estados', [CasaController::class, 'snapshot'])
        ->middleware(['abilities:casa:write', 'api.write.role']);
});
