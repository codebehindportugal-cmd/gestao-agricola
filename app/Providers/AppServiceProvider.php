<?php

namespace App\Providers;

use App\Models\Cultura;
use App\Models\Compromisso;
use App\Models\Despesa;
use App\Models\Equipa;
use App\Models\Funcionario;
use App\Models\Alfaia;
use App\Models\Campanha;
use App\Models\Maquina;
use App\Models\Operacao;
use App\Models\Parcela;
use App\Models\Terreno;
use App\Policies\CulturaPolicy;
use App\Policies\CampanhaPolicy;
use App\Policies\CompromissoPolicy;
use App\Policies\DespesaPolicy;
use App\Policies\EquipaPolicy;
use App\Policies\FuncionarioPolicy;
use App\Policies\AlfaiaPolicy;
use App\Policies\MaquinaPolicy;
use App\Policies\OperacaoPolicy;
use App\Policies\ParcelaPolicy;
use App\Policies\TerrenoPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Http\Request;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        Gate::policy(Terreno::class, TerrenoPolicy::class);
        Gate::policy(Parcela::class, ParcelaPolicy::class);
        Gate::policy(Cultura::class, CulturaPolicy::class);
        Gate::policy(Campanha::class, CampanhaPolicy::class);
        Gate::policy(Operacao::class, OperacaoPolicy::class);
        Gate::policy(Funcionario::class, FuncionarioPolicy::class);
        Gate::policy(Equipa::class, EquipaPolicy::class);
        Gate::policy(Maquina::class, MaquinaPolicy::class);
        Gate::policy(Alfaia::class, AlfaiaPolicy::class);
        Gate::policy(Despesa::class, DespesaPolicy::class);
        Gate::policy(Compromisso::class, CompromissoPolicy::class);

        Vite::prefetch(concurrency: 3);
    }
}
