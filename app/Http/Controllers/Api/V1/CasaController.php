<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\RespondeJson;
use App\Http\Controllers\Controller;
use App\Models\CasaDispositivo;
use App\Models\CasaEvento;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Ingestão dos dispositivos da casa (Aqara e afins) vindos do Home Assistant.
 *
 * O sentido é sempre casa -> site. O Home Assistant faz POST aqui; este
 * servidor nunca inicia ligações para a rede de casa, o que dispensa túneis,
 * portas abertas no router e tokens do HA guardados no VPS.
 */
class CasaController extends Controller
{
    use RespondeJson;

    /**
     * Um accionamento isolado: movimento, porta, janela.
     * Actualiza o estado do dispositivo e, quando aplicável, grava histórico.
     */
    public function evento(Request $request): JsonResponse
    {
        $dados = $request->validate([
            'entity_id' => ['required', 'string', 'max:255'],
            'nome' => ['nullable', 'string', 'max:255'],
            'zona' => ['nullable', 'string', 'max:100'],
            'tipo' => ['nullable', 'string', 'max:50'],
            'device_class' => ['nullable', 'string', 'max:50'],
            'estado' => ['required', 'string', 'max:100'],
            'ocorreu_em' => ['nullable', 'date'],
            'atributos' => ['nullable', 'array'],
        ]);

        $avisos = [];

        // O tipo pode vir explícito ou ser deduzido do device_class do HA.
        $tipo = $dados['tipo']
            ?? CasaDispositivo::tipoDeDeviceClass($dados['device_class'] ?? null);

        if ($tipo === 'outro') {
            $avisos[] = "Tipo não reconhecido para {$dados['entity_id']}; guardado como 'outro'.";
        }

        $dispositivo = CasaDispositivo::updateOrCreate(
            ['entity_id' => $dados['entity_id']],
            [
                'nome' => $dados['nome'] ?: $dados['entity_id'],
                'zona' => $dados['zona'] ?: null,
                'tipo' => $tipo,
                'estado' => $dados['estado'],
                'atributos' => $dados['atributos'] ?? null,
                'visto_em' => now(),
            ]
        );

        $gravouEvento = false;

        // Só o arranque interessa como evento. O 'off' é fim de detecção e já
        // está refletido no estado do dispositivo — gravá-lo duplicava a linha
        // temporal sem acrescentar informação.
        if ($dispositivo->geraHistorico() && $dados['estado'] === 'on') {
            CasaEvento::create([
                'entity_id' => $dispositivo->entity_id,
                'nome' => $dispositivo->nome,
                'zona' => $dispositivo->zona,
                'tipo' => $dispositivo->tipo,
                'estado' => $dados['estado'],
                'ocorreu_em' => isset($dados['ocorreu_em'])
                    ? Carbon::parse($dados['ocorreu_em'])
                    : now(),
            ]);

            $gravouEvento = true;
        }

        return $this->ok([
            'dispositivo_id' => $dispositivo->id,
            'tipo' => $dispositivo->tipo,
            'evento_gravado' => $gravouEvento,
        ], $avisos);
    }

    /**
     * Fotografia completa dos estados, enviada periodicamente pelo HA.
     *
     * Existe porque os eventos isolados podem perder-se (site em deploy, rede
     * em baixo) e o painel ficaria a mostrar um estado antigo para sempre.
     * Também é isto que alimenta o aviso de "sem contacto da casa".
     */
    public function snapshot(Request $request): JsonResponse
    {
        $dados = $request->validate([
            'dispositivos' => ['required', 'array', 'max:300'],
            'dispositivos.*.entity_id' => ['required', 'string', 'max:255'],
            'dispositivos.*.nome' => ['nullable', 'string', 'max:255'],
            'dispositivos.*.zona' => ['nullable', 'string', 'max:100'],
            'dispositivos.*.tipo' => ['nullable', 'string', 'max:50'],
            'dispositivos.*.device_class' => ['nullable', 'string', 'max:50'],
            'dispositivos.*.estado' => ['required', 'string', 'max:100'],
        ]);

        $agora = now();
        $tratados = 0;

        foreach ($dados['dispositivos'] as $d) {
            CasaDispositivo::updateOrCreate(
                ['entity_id' => $d['entity_id']],
                [
                    'nome' => ($d['nome'] ?? '') ?: $d['entity_id'],
                    'zona' => ($d['zona'] ?? '') ?: null,
                    'tipo' => $d['tipo']
                        ?? CasaDispositivo::tipoDeDeviceClass($d['device_class'] ?? null),
                    'estado' => $d['estado'],
                    'visto_em' => $agora,
                ]
            );

            $tratados++;
        }

        return $this->ok([
            'dispositivos_actualizados' => $tratados,
            'em' => $agora->toIso8601String(),
        ]);
    }
}
