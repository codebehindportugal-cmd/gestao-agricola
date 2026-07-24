<?php

namespace App\Http\Middleware;

use App\Models\Campanha;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();
        $permissions = $user ? $user->getAllPermissions() : collect();
        $campanhas = collect();
        $campanhaAtiva = null;

        if ($user && Schema::hasTable('campanhas')) {
            $campanhasBase = Campanha::query()
                ->orderByDesc('ano')
                ->orderByDesc('id')
                ->get(['id', 'ano', 'status']);

            $campanhas = $campanhasBase
                ->groupBy('ano')
                ->map(fn ($campanhasAno, $ano) => [
                    'id' => (int) $ano,
                    'ano' => (int) $ano,
                    'nome' => $this->seasonLabel((int) $ano),
                    'status' => $campanhasAno->contains('status', 'em_curso') ? 'em_curso' : $campanhasAno->first()?->status,
                ])
                ->sortByDesc('ano')
                ->values()
                ->take(12);

            $defaultCampaign = $campanhas->firstWhere('status', 'em_curso') ?? $campanhas->first();
            $activeYear = $request->session()->get('campanha_ativa_ano')
                ?: optional($campanhasBase->firstWhere('id', $request->session()->get('campanha_ativa_id')))->ano
                ?: ($defaultCampaign['ano'] ?? null);

            $campanhaAtiva = $activeYear
                ? $campanhas->firstWhere('ano', (int) $activeYear)
                : null;

            if (! $campanhaAtiva && $defaultCampaign) {
                $campanhaAtiva = $defaultCampaign;
            }
        }

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $user,
                'permissions' => $permissions,
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
            ],
            'workingCampaign' => [
                'active' => $campanhaAtiva,
                'options' => $campanhas,
            ],
        ];
    }

    private function seasonLabel(int $ano): string
    {
        return 'Campanha '.($ano - 1).'/'.$ano;
    }
}
