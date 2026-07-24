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
            $campanhas = Campanha::query()
                ->with('cultura:id,nome')
                ->orderByDesc('ano')
                ->orderByDesc('id')
                ->limit(30)
                ->get()
                ->map(fn (Campanha $campanha) => [
                    'id' => $campanha->id,
                    'nome' => trim(($campanha->cultura?->nome ? $campanha->cultura->nome.' ' : '').$campanha->ano),
                    'ano' => $campanha->ano,
                    'status' => $campanha->status,
                ]);

            $defaultCampaign = $campanhas->firstWhere('status', 'em_curso') ?? $campanhas->first();
            $activeId = $request->session()->get('campanha_ativa_id') ?: ($defaultCampaign['id'] ?? null);

            $campanhaAtiva = $activeId
                ? $campanhas->firstWhere('id', (int) $activeId)
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
}
