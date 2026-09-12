<?php

namespace App\Http\Controllers;

use App\Models\CasaDispositivo;
use App\Models\CasaEvento;
use App\Models\Compromisso;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Painel de casa: câmaras, accionamentos dos sensores e o calendário de
 * compromissos, para o ecrã do escritório.
 *
 * O vídeo não passa aqui — a página aponta diretamente ao go2rtc da rede de
 * casa (ver config/casa.php). Este controlador serve só os dados.
 */
class CasaPainelController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('Casa/Index', [
            'baseUrl' => config('casa.base_url'),
            'cameras' => config('casa.cameras'),
            'modoVideo' => config('casa.modo_video'),
            'intervaloFeed' => config('casa.intervalo_feed'),
            'minutosSemContacto' => config('casa.minutos_sem_contacto'),
            'kiosk' => $request->boolean('kiosk'),
            'feed' => $this->dados($request),
        ]);
    }

    public function feed(Request $request): JsonResponse
    {
        return response()->json($this->dados($request));
    }

    private function dados(Request $request): array
    {
        $agora = CarbonImmutable::now();

        return [
            'dispositivos' => CasaDispositivo::visiveis()
                ->orderBy('ordem')
                ->orderBy('zona')
                ->orderBy('nome')
                ->get(['entity_id', 'nome', 'zona', 'tipo', 'estado', 'visto_em']),

            'eventos' => CasaEvento::query()
                ->latest('ocorreu_em')
                ->limit(config('casa.eventos_visiveis'))
                ->get(['id', 'nome', 'zona', 'tipo', 'ocorreu_em']),

            'ultimoContacto' => CasaDispositivo::max('visto_em'),

            'calendario' => $this->calendario($request, $agora),

            'agora' => $agora->toIso8601String(),
        ];
    }

    /**
     * Compromissos do calendário já existente (tabela compromissos).
     *
     * Respeita a política: um utilizador de kiosk sem permissão para ver
     * compromissos continua a ver as câmaras e os sensores, e a coluna do
     * calendário simplesmente não aparece.
     */
    private function calendario(Request $request, CarbonImmutable $agora): ?array
    {
        if ($request->user()?->can('viewAny', Compromisso::class) !== true) {
            return null;
        }

        $colunas = [
            'id', 'titulo', 'categoria', 'tipo', 'entidade',
            'data', 'hora', 'valor', 'estado',
        ];

        $hoje = $agora->toDateString();
        $limite = $agora->addDays(config('casa.dias_calendario'))->toDateString();

        return [
            'hoje' => Compromisso::pendentes()
                ->whereDate('data', $hoje)
                ->orderByRaw('hora IS NULL, hora')
                ->get($colunas),

            'proximos' => Compromisso::pendentes()
                ->whereDate('data', '>', $hoje)
                ->whereDate('data', '<=', $limite)
                ->orderBy('data')
                ->orderByRaw('hora IS NULL, hora')
                ->limit(15)
                ->get($colunas),

            'atrasados' => Compromisso::atrasados()
                ->orderBy('data')
                ->limit(10)
                ->get($colunas),
        ];
    }
}
