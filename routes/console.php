<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use App\Models\CasaEvento;
use App\Models\Operacao;
use App\Support\StockConsumption;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('stock:reconciliar-operacoes {--operacao=}', function () {
    $query = Operacao::query()
        ->with('produtos')
        ->when($this->option('operacao'), fn ($query, $id) => $query->whereKey($id));

    $count = 0;

    $query->orderBy('id')->chunkById(100, function ($operacoes) use (&$count) {
        foreach ($operacoes as $operacao) {
            StockConsumption::reconcileOperation($operacao);
            $count++;
        }
    });

    $this->info("Stock reconciliado para {$count} operacoes.");
})->purpose('Aplica ao stock os consumos de produtos e combustivel das operacoes ja registadas.');

// Limpeza do historico da casa. A linha temporal do painel e' de vigilancia
// recente, nao um arquivo: sem isto a tabela cresce indefinidamente com
// accionamentos de movimento.
Schedule::call(function () {
    CasaEvento::where('ocorreu_em', '<', now()->subDays(config('casa.retencao_dias')))->delete();
})->dailyAt('04:10')->name('casa:limpar-eventos')->withoutOverlapping();
