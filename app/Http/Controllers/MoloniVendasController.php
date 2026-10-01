<?php

namespace App\Http\Controllers;

use App\Models\Campanha;
use App\Models\Despesa;
use App\Services\Moloni\ImportadorVendasMoloni;
use App\Services\Moloni\MoloniException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Botao "Importar do Moloni" no ecra das despesas/vendas.
 *
 * Importa as vendas da campanha activa (ou da indicada), do inicio da
 * campanha ate hoje. Pode carregar-se as vezes que se quiser: o que ja entrou
 * nao se repete.
 */
class MoloniVendasController extends Controller
{
    public function __invoke(Request $request, ImportadorVendasMoloni $importador): RedirectResponse
    {
        $this->authorize('create', Despesa::class);

        $dados = $request->validate([
            'campanha_id' => ['nullable', 'integer', 'exists:campanhas,id'],
        ]);

        $campanha = isset($dados['campanha_id'])
            ? Campanha::query()->findOrFail($dados['campanha_id'])
            : $this->campanhaActual($request);

        if ($campanha === null) {
            return back()->with('error', 'Nao ha nenhuma campanha para onde importar as vendas.');
        }

        try {
            $resumo = $importador->importar($campanha);
        } catch (MoloniException $e) {
            report($e);

            return back()->with('error', 'Moloni: '.$e->getMessage());
        }

        $mensagem = sprintf(
            'Moloni (%s a %s): %d documento(s), %d venda(s) nova(s) — %s%s%s.',
            date('d/m/Y', strtotime($resumo['de'])),
            date('d/m/Y', strtotime($resumo['ate'])),
            $resumo['documentos'],
            $resumo['criadas'],
            number_format($resumo['valor'], 2, ',', ' ').' €',
            $resumo['existentes'] ? ", {$resumo['existentes']} já tinham entrado" : '',
            $resumo['sem_especie'] ? ", {$resumo['sem_especie']} linha(s) sem espécie reconhecida (ficaram como 'outro')" : '',
        );

        if ($resumo['avisos'] !== []) {
            $mensagem .= ' '.implode(' ', array_slice($resumo['avisos'], 0, 5));
        }

        return back()->with('success', $mensagem);
    }

    /** A campanha que contem hoje; senao a mais recente. */
    private function campanhaActual(Request $request): ?Campanha
    {
        $ano = $request->session()->get('campanha_ativa_ano');

        $query = Campanha::query()->when($ano, fn ($q) => $q->where('ano', $ano));

        return (clone $query)
            ->whereDate('data_inicio', '<=', today())
            ->where(fn ($q) => $q->whereNull('data_fim')->orWhereDate('data_fim', '>=', today()))
            ->orderByDesc('data_inicio')
            ->first()
            ?? (clone $query)->orderByDesc('data_inicio')->first()
            ?? Campanha::query()->orderByDesc('data_inicio')->first();
    }
}
