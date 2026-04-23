<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ParcelaManagementController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\TerrenoManagementController;
use App\Http\Controllers\CulturaManagementController;
use App\Http\Controllers\MaquinariaManagementController;
use App\Http\Controllers\MaoObraManagementController;
use App\Http\Controllers\OperacaoManagementController;
use App\Http\Controllers\CampanhaController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\PermissionController;
use App\Http\Controllers\StockManagementController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'appName' => config('app.name', 'Gestão Agrícola'),
        'laravelVersion' => Application::VERSION,
    ]);
});

Route::get('/dashboard', DashboardController::class)
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/terrenos', [TerrenoManagementController::class, 'index'])->name('app.terrenos.index');
    Route::get('/terrenos/criar', [TerrenoManagementController::class, 'create'])->name('app.terrenos.create');
    Route::post('/terrenos', [TerrenoManagementController::class, 'store'])->name('app.terrenos.store');
    Route::get('/terrenos/{terreno}/editar', [TerrenoManagementController::class, 'edit'])->name('app.terrenos.edit');
    Route::patch('/terrenos/{terreno}', [TerrenoManagementController::class, 'update'])->name('app.terrenos.update');
    Route::delete('/terrenos/{terreno}', [TerrenoManagementController::class, 'destroy'])->name('app.terrenos.destroy');
    Route::get('/parcelas', [ParcelaManagementController::class, 'index'])->name('app.parcelas.index');
    Route::get('/parcelas/criar', [ParcelaManagementController::class, 'create'])->name('app.parcelas.create');
    Route::post('/parcelas', [ParcelaManagementController::class, 'store'])->name('app.parcelas.store');
    Route::get('/parcelas/{parcela}/editar', [ParcelaManagementController::class, 'edit'])->name('app.parcelas.edit');
    Route::patch('/parcelas/{parcela}', [ParcelaManagementController::class, 'update'])->name('app.parcelas.update');
    Route::delete('/parcelas/{parcela}', [ParcelaManagementController::class, 'destroy'])->name('app.parcelas.destroy');
    Route::get('/culturas', [CulturaManagementController::class, 'index'])->name('app.culturas.index');
    Route::post('/culturas', [CulturaManagementController::class, 'store'])->name('app.culturas.store');
    Route::patch('/culturas/{cultura}', [CulturaManagementController::class, 'update'])->name('app.culturas.update');
    Route::delete('/culturas/{cultura}', [CulturaManagementController::class, 'destroy'])->name('app.culturas.destroy');
    Route::get('/campanhas', [CampanhaController::class, 'index'])->name('app.campanhas.index');
    Route::get('/campanhas/{campanha}/exportar', [CampanhaController::class, 'exportar'])->name('app.campanhas.exportar');
    Route::get('/operacoes', [OperacaoManagementController::class, 'index'])->name('app.operacoes.index');
    Route::post('/operacoes', [OperacaoManagementController::class, 'store'])->name('app.operacoes.store');
    Route::post('/operacoes/produtos', [OperacaoManagementController::class, 'storeProduto'])->name('app.operacoes.produtos.store');
    Route::patch('/operacoes/{operacao}', [OperacaoManagementController::class, 'update'])->name('app.operacoes.update');
    Route::delete('/operacoes/{operacao}', [OperacaoManagementController::class, 'destroy'])->name('app.operacoes.destroy');
    Route::get('/maquinaria', [MaquinariaManagementController::class, 'index'])->name('app.maquinaria.index');
    Route::post('/maquinaria/maquinas', [MaquinariaManagementController::class, 'storeMaquina'])->name('app.maquinas.store');
    Route::patch('/maquinaria/maquinas/{maquina}', [MaquinariaManagementController::class, 'updateMaquina'])->name('app.maquinas.update');
    Route::delete('/maquinaria/maquinas/{maquina}', [MaquinariaManagementController::class, 'destroyMaquina'])->name('app.maquinas.destroy');
    Route::post('/maquinaria/alfaias', [MaquinariaManagementController::class, 'storeAlfaia'])->name('app.alfaias.store');
    Route::patch('/maquinaria/alfaias/{alfaia}', [MaquinariaManagementController::class, 'updateAlfaia'])->name('app.alfaias.update');
    Route::delete('/maquinaria/alfaias/{alfaia}', [MaquinariaManagementController::class, 'destroyAlfaia'])->name('app.alfaias.destroy');
    Route::post('/maquinaria/revisoes', [MaquinariaManagementController::class, 'storeRevisao'])->name('app.revisoes.store');
    Route::patch('/maquinaria/revisoes/{revisao}', [MaquinariaManagementController::class, 'updateRevisao'])->name('app.revisoes.update');
    Route::delete('/maquinaria/revisoes/{revisao}', [MaquinariaManagementController::class, 'destroyRevisao'])->name('app.revisoes.destroy');
    Route::get('/stock', [StockManagementController::class, 'index'])->name('app.stock.index');
    Route::post('/stock/produtos', [StockManagementController::class, 'storeProduto'])->name('app.stock.produtos.store');
    Route::patch('/stock/produtos/{produto}', [StockManagementController::class, 'update'])->name('app.stock.update');
    Route::get('/mao-obra', [MaoObraManagementController::class, 'index'])->name('app.mao-obra.index');
    Route::post('/mao-obra/operarios', [MaoObraManagementController::class, 'storeFuncionario'])->name('app.funcionarios.store');
    Route::patch('/mao-obra/operarios/{funcionario}', [MaoObraManagementController::class, 'updateFuncionario'])->name('app.funcionarios.update');
    Route::delete('/mao-obra/operarios/{funcionario}', [MaoObraManagementController::class, 'destroyFuncionario'])->name('app.funcionarios.destroy');
    Route::post('/mao-obra/equipas', [MaoObraManagementController::class, 'storeEquipa'])->name('app.equipas.store');
    Route::patch('/mao-obra/equipas/{equipa}', [MaoObraManagementController::class, 'updateEquipa'])->name('app.equipas.update');
    Route::delete('/mao-obra/equipas/{equipa}', [MaoObraManagementController::class, 'destroyEquipa'])->name('app.equipas.destroy');

    // Users
    Route::middleware('permission:usuarios.manage')->group(function () {
        Route::get('/users', [UserController::class, 'index'])->name('users.index');
        Route::get('/users/create', [UserController::class, 'create'])->name('users.create');
        Route::post('/users', [UserController::class, 'store'])->name('users.store');
        Route::get('/users/{user}/edit', [UserController::class, 'edit'])->name('users.edit');
        Route::patch('/users/{user}', [UserController::class, 'update'])->name('users.update');
        Route::delete('/users/{user}', [UserController::class, 'destroy'])->name('users.destroy');
    });

    // Roles
    Route::middleware('permission:usuarios.manage')->group(function () {
        Route::get('/roles', [RoleController::class, 'index'])->name('roles.index');
        Route::get('/roles/create', [RoleController::class, 'create'])->name('roles.create');
        Route::post('/roles', [RoleController::class, 'store'])->name('roles.store');
        Route::get('/roles/{role}/edit', [RoleController::class, 'edit'])->name('roles.edit');
        Route::patch('/roles/{role}', [RoleController::class, 'update'])->name('roles.update');
        Route::delete('/roles/{role}', [RoleController::class, 'destroy'])->name('roles.destroy');
    });

    // Permissions
    Route::middleware('permission:usuarios.manage')->group(function () {
        Route::get('/permissions', [PermissionController::class, 'index'])->name('permissions.index');
        Route::get('/permissions/create', [PermissionController::class, 'create'])->name('permissions.create');
        Route::post('/permissions', [PermissionController::class, 'store'])->name('permissions.store');
        Route::get('/permissions/{permission}/edit', [PermissionController::class, 'edit'])->name('permissions.edit');
        Route::patch('/permissions/{permission}', [PermissionController::class, 'update'])->name('permissions.update');
        Route::delete('/permissions/{permission}', [PermissionController::class, 'destroy'])->name('permissions.destroy');
    });

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
