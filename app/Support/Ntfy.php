<?php

namespace App\Support;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Notificacoes push para a app ntfy do telemovel.
 *
 * Igual ao painel Ateneya, e pelas mesmas duas razoes:
 *
 * 1. So se avisa uma vez por cada coisa. Um compromisso a 30 dias da tres
 *    avisos (30, 7 e 1 dia) e nao trinta; quem o recebe todos os dias deixa de
 *    o ler.
 *
 * 2. Nunca deita nada abaixo. Se o ntfy nao responder, fica no log e a tarefa
 *    segue. Nao conseguir avisar de um prazo nao pode ser um segundo problema.
 */
class Ntfy
{
    /** Prazo a chegar. */
    public static function aviso(string $tipo, string $titulo, string $mensagem, ?string $link = null): bool
    {
        return self::enviar($tipo, $titulo, $mensagem, tags: 'calendar', link: $link);
    }

    /** Prazo ja passado. */
    public static function atraso(string $tipo, string $titulo, string $mensagem, ?string $link = null): bool
    {
        return self::enviar($tipo, $titulo, $mensagem, prioridade: 'high', tags: 'rotating_light', link: $link);
    }

    public static function enviar(
        string $tipo,
        string $titulo,
        string $mensagem,
        string $prioridade = 'default',
        string $tags = 'bell',
        ?string $link = null,
    ): bool {
        if (! config('ntfy.enabled') || blank(config('ntfy.topic'))) {
            return false;
        }

        if ($tipo !== 'teste' && ! config("ntfy.avisos.{$tipo}", true)) {
            return false;
        }

        $cabecalhos = [
            'Title' => $titulo,
            'Priority' => $prioridade,
            'Tags' => $tags,
            'Markdown' => 'yes',
        ];

        if ($link) {
            $cabecalhos['Click'] = $link;
        }

        if ($token = config('ntfy.token')) {
            $cabecalhos['Authorization'] = 'Bearer '.$token;
        }

        try {
            $resposta = Http::withHeaders($cabecalhos)
                ->timeout((int) config('ntfy.timeout'))
                ->withBody($mensagem, 'text/plain')
                ->post(config('ntfy.url').'/'.config('ntfy.topic'));

            if (! $resposta->successful()) {
                Log::warning('ntfy recusou a notificação', ['status' => $resposta->status(), 'titulo' => $titulo]);
            }

            return $resposta->successful();
        } catch (Throwable $e) {
            // De propósito engolido: nunca parar uma tarefa por causa disto.
            Log::warning('ntfy inacessível: '.$e->getMessage(), ['titulo' => $titulo]);

            return false;
        }
    }
}
