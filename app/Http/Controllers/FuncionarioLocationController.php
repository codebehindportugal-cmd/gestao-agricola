<?php

namespace App\Http\Controllers;

use App\Models\Funcionario;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class FuncionarioLocationController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Funcionario::class);

        $funcionarios = Funcionario::query()
            ->where('status', 'ativo')
            ->orderBy('nome')
            ->get()
            ->map(fn (Funcionario $funcionario) => $this->serializeFuncionario($funcionario, true));

        return Inertia::render('MaoObra/Localizacoes', [
            'funcionarios' => $funcionarios,
            'summary' => [
                'ativos' => $funcionarios->count(),
                'com_localizacao' => $funcionarios->where('has_location', true)->count(),
                'recentes' => $funcionarios->filter(fn ($funcionario) => $funcionario['location_shared_at'] && now()->diffInMinutes($funcionario['location_shared_at']) <= 15)->count(),
            ],
        ]);
    }

    public function refreshToken(Request $request, Funcionario $funcionario): RedirectResponse
    {
        $this->authorize('update', $funcionario);

        $funcionario->forceFill([
            'location_token' => Funcionario::generateLocationToken(),
            'location_token_refreshed_at' => now(),
        ])->save();

        return back()->with('success', 'Link de localização renovado com sucesso.');
    }

    public function show(string $token): Response
    {
        $funcionario = $this->findByToken($token);

        return Inertia::render('MaoObra/PartilharLocalizacao', [
            'funcionario' => [
                'nome' => $funcionario->nome,
                'cargo' => $funcionario->cargo,
                'last_shared_at' => optional($funcionario->location_shared_at)?->toIso8601String(),
            ],
            'token' => $token,
        ]);
    }

    public function store(Request $request, string $token): JsonResponse
    {
        $funcionario = $this->findByToken($token);

        $data = $request->validate([
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'accuracy' => ['nullable', 'numeric', 'min:0', 'max:100000'],
            'speed' => ['nullable', 'numeric', 'min:0', 'max:1000'],
            'heading' => ['nullable', 'numeric', 'between:0,360'],
        ]);

        $funcionario->forceFill([
            'last_latitude' => $data['latitude'],
            'last_longitude' => $data['longitude'],
            'last_accuracy' => isset($data['accuracy']) ? (int) round($data['accuracy']) : null,
            'last_speed' => $data['speed'] ?? null,
            'last_heading' => $data['heading'] ?? null,
            'location_shared_at' => now(),
        ])->save();

        return response()->json([
            'message' => 'Localização atualizada.',
            'funcionario' => $this->serializeFuncionario($funcionario->fresh(), false),
        ]);
    }

    private function findByToken(string $token): Funcionario
    {
        return Funcionario::query()
            ->where('location_token', $token)
            ->where('status', 'ativo')
            ->firstOrFail();
    }

    private function serializeFuncionario(Funcionario $funcionario, bool $includeShareUrl): array
    {
        return [
            'id' => $funcionario->id,
            'nome' => $funcionario->nome,
            'cargo' => $funcionario->cargo,
            'telefone' => $funcionario->telefone,
            'has_location' => $funcionario->hasLocation(),
            'latitude' => $funcionario->last_latitude !== null ? (float) $funcionario->last_latitude : null,
            'longitude' => $funcionario->last_longitude !== null ? (float) $funcionario->last_longitude : null,
            'accuracy' => $funcionario->last_accuracy,
            'speed' => $funcionario->last_speed !== null ? (float) $funcionario->last_speed : null,
            'heading' => $funcionario->last_heading !== null ? (float) $funcionario->last_heading : null,
            'location_shared_at' => optional($funcionario->location_shared_at)?->toIso8601String(),
            'share_url' => $includeShareUrl ? route('funcionarios.localizacao.share', $funcionario->location_token) : null,
        ];
    }
}
