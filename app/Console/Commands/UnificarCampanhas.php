<?php

namespace App\Console\Commands;

use App\Models\Campanha;
use App\Models\Custo;
use App\Models\Despesa;
use App\Models\Parcela;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Throwable;

/**
 * Junta todas as campanhas de uma epoca numa so.
 *
 * O agri:migrar-campanhas tinha feito uma campanha por especie e ano
 * (Pereiras 2026, Macieiras 2026, Culturas_anuais 2026). Na pratica isso
 * deixava quase tudo sem campanha: os periodos sao iguais, a API nao
 * conseguia escolher entre elas e as faturas ficavam "gerais, rateaveis"
 * quando na verdade sao da epoca inteira. Passa a haver uma campanha - a
 * epoca - e a margem por especie sai da cultura/parcela dentro dela.
 *
 * Nao corre sozinho: e um comando e nao uma migracao, para nao disparar num
 * deploy. Sem --confirmar mostra apenas o plano.
 */
class UnificarCampanhas extends Command
{
    protected $signature = 'agri:unificar-campanhas
        {--nome=2025/2026 : Nome da campanha única}
        {--inicio=2025-10-01 : Início da época, AAAA-MM-DD}
        {--fim=2026-09-30 : Fim da época, AAAA-MM-DD}
        {--campanhas= : Ids a absorver, separados por vírgula (por omissão, todas as que tocam a época)}
        {--manter-rateaveis : Deixa os custos partilhados sem campanha, como estão}
        {--apagar : Apaga de vez as campanhas que a aplicação inventou; as outras ficam arquivadas}
        {--confirmar : Aplica as alterações; sem esta opção apenas mostra o plano}';

    protected $description = 'Junta as campanhas da época numa só e repõe tudo o que lhes apontava.';

    /** Tabelas que nao se repontam com um UPDATE simples. */
    private const TABELAS_ESPECIAIS = ['campanha_parcela'];

    public function handle(): int
    {
        $aplicar = (bool) $this->option('confirmar');
        $nome = trim((string) $this->option('nome'));
        $inicio = CarbonImmutable::parse((string) $this->option('inicio'))->toDateString();
        $fim = CarbonImmutable::parse((string) $this->option('fim'))->toDateString();
        $ano = CarbonImmutable::parse($fim)->year;

        if ($nome === '') {
            $this->error('Indique o nome da campanha (--nome).');

            return self::FAILURE;
        }

        $destino = Campanha::query()->where('nome', $nome)->first();
        $absorvidas = $this->campanhasAAbsorver($inicio, $fim, $destino);

        $this->info($aplicar
            ? "A unificar as campanhas em \"{$nome}\"..."
            : "PLANO (nada será alterado sem --confirmar)");
        $this->line("Época: {$inicio} a {$fim}");
        $this->line($destino
            ? "Destino: campanha #{$destino->id} \"{$nome}\", que já existe."
            : "Destino: campanha nova \"{$nome}\".");
        $this->newLine();

        if ($absorvidas->isEmpty()) {
            $this->warn('Nenhuma campanha para absorver. Nada a fazer.');

            return self::SUCCESS;
        }

        $tabelas = $this->tabelasComCampanha();

        // Sem a lista de tabelas nada seria repontado e, pior, a verificacao
        // de "ficou vazia" daria zero e arquivava campanhas com registos ainda
        // pendurados nelas.
        if ($tabelas === []) {
            $this->error('Não foi possível ler as tabelas com campanha_id. Nada foi alterado.');

            return self::FAILURE;
        }

        $idsAntigos = $absorvidas->pluck('id')->all();
        $contagens = $this->contarPorTabela($tabelas, $idsAntigos);
        $parcelaIds = $this->parcelasDas($absorvidas);

        $this->line('<fg=cyan>Campanhas a absorver</>');

        $this->line((bool) $this->option('apagar')
            ? '  (as inventadas pela aplicação são apagadas; as outras ficam arquivadas)'
            : '  (ficam todas arquivadas: somem do ecrã, o registo fica)');

        foreach ($absorvidas as $campanha) {
            $this->line(sprintf(
                '  #%d %s (%s a %s) — %s',
                $campanha->id,
                $campanha->nome_completo,
                $campanha->data_inicio?->toDateString() ?? '—',
                $campanha->data_fim?->toDateString() ?? '—',
                $this->destinoDaCampanha($campanha)
            ));
        }

        $this->newLine();
        $this->line('<fg=cyan>Registos a repontar</>');

        foreach ($contagens as $tabela => $total) {
            $this->line(sprintf('  %-24s %d', $tabela, $total));
        }

        if ($contagens === []) {
            $this->line('  (nenhum)');
        }

        $this->newLine();
        $this->line('<fg=cyan>Parcelas a ligar</>: '.($parcelaIds === [] ? '(nenhuma)' : count($parcelaIds).' — '.
            Parcela::query()->whereIn('id', $parcelaIds)->pluck('nome')->implode(', ')));

        $manterRateaveis = (bool) $this->option('manter-rateaveis');
        $destinoOuNada = $manterRateaveis ? ' (ficam como estão)' : ' → passam para a campanha';

        $this->newLine();
        $this->line('<fg=cyan>Sem campanha, dentro da época</>');
        $this->line(sprintf('  custos partilhados (rateáveis)  %d%s', $this->custosPartilhadosDaEpoca($inicio, $fim)->count(), $destinoOuNada));
        $this->line(sprintf('  despesas gerais sem equipamento %d%s', $this->despesasGeraisDaEpoca($inicio, $fim)->count(), $destinoOuNada));

        if (! $aplicar) {
            $this->newLine();
            $this->comment('Volte a correr com --confirmar para aplicar.');

            return self::SUCCESS;
        }

        try {
            $relatorio = DB::transaction(function () use (
                $destino,
                $nome,
                $ano,
                $inicio,
                $fim,
                $absorvidas,
                $idsAntigos,
                $tabelas,
                $parcelaIds,
                $manterRateaveis
            ): array {
                $destino ??= Campanha::query()->create([
                    'nome' => $nome,
                    'cultura_id' => null,
                    'ano' => $ano,
                    'data_inicio' => $inicio,
                    'data_fim' => $fim,
                    'status' => 'em_curso',
                    'observacoes' => 'Campanha única da época '.$nome.'.',
                ]);

                // O destino nao se absorve a si proprio, mesmo que tenha sido
                // apanhado pelo filtro do periodo.
                $idsAntigos = array_values(array_diff($idsAntigos, [$destino->id]));

                $destino->parcelas()->syncWithoutDetaching($parcelaIds);

                $movidos = [];

                // campanha_parcela tem unique(campanha_id, parcela_id): um
                // UPDATE cego rebentava com a chave assim que duas campanhas
                // partilhassem uma parcela. As ligacoes ja foram copiadas em
                // cima; aqui so se apagam as antigas.
                $movidos['campanha_parcela'] = DB::table('campanha_parcela')
                    ->whereIn('campanha_id', $idsAntigos)
                    ->delete();

                foreach ($tabelas as $tabela) {
                    $movidos[$tabela] = DB::table($tabela)
                        ->whereIn('campanha_id', $idsAntigos)
                        ->update(['campanha_id' => $destino->id]);
                }

                $orfaos = ['custos' => 0, 'despesas' => 0];

                if (! $manterRateaveis) {
                    // Com uma campanha so, um custo "partilhado" e da epoca:
                    // deixa-lo sem campanha era deixa-lo fora do custo/kg. A
                    // marca rateavel e a base ficam, para o rateio por
                    // cultura/parcela dentro da campanha as poder usar.
                    $orfaos['custos'] = $this->custosPartilhadosDaEpoca($inicio, $fim)
                        ->update(['campanha_id' => $destino->id]);

                    $orfaos['despesas'] = $this->despesasGeraisDaEpoca($inicio, $fim)
                        ->update(['campanha_id' => $destino->id]);
                }

                // So se mexe no que ficou mesmo vazio: uma campanha com registos
                // ainda pendurados desaparecia do ecra e levava-os com ela.
                //
                // Arquivar (soft delete) e o normal: some do ecra e o registo
                // fica. Com --apagar, so as que a aplicacao inventou sozinha
                // saem da base de dados — essas nunca foram nada e nao ha nada
                // para guardar. As que foram criadas de proposito (as gerais do
                // agri:migrar-campanhas) ficam arquivadas na mesma.
                $podeApagar = (bool) $this->option('apagar');
                $apagadas = [];
                $arquivadas = [];
                $ocupadas = [];

                foreach ($absorvidas as $campanha) {
                    if ($campanha->id === $destino->id) {
                        continue;
                    }

                    $restantes = $this->contarPorTabela($tabelas, [$campanha->id]);

                    if (array_sum($restantes) > 0) {
                        $ocupadas[$campanha->id] = $restantes;

                        continue;
                    }

                    if ($podeApagar && $this->foiInventada($campanha)) {
                        $campanha->forceDelete();
                        $apagadas[] = $campanha->id;

                        continue;
                    }

                    $campanha->delete();
                    $arquivadas[] = $campanha->id;
                }

                return [
                    'destino' => $destino,
                    'movidos' => $movidos,
                    'apagadas' => $apagadas,
                    'arquivadas' => $arquivadas,
                    'ocupadas' => $ocupadas,
                    'orfaos' => $orfaos,
                ];
            });
        } catch (Throwable $excepcao) {
            $this->error('Nada foi alterado: '.$excepcao->getMessage());

            return self::FAILURE;
        }

        $this->newLine();
        $this->info('Aplicado.');
        $this->line("Campanha: #{$relatorio['destino']->id} \"{$relatorio['destino']->nome}\"");

        foreach ($relatorio['movidos'] as $tabela => $total) {
            if ($total > 0) {
                $this->line(sprintf(
                    '  %-24s %d %s',
                    $tabela,
                    $total,
                    $tabela === 'campanha_parcela' ? 'ligações antigas removidas' : 'repontados'
                ));
            }
        }

        if (! $manterRateaveis) {
            $this->line(sprintf('  %-24s %d', 'custos sem campanha', $relatorio['orfaos']['custos']));
            $this->line(sprintf('  %-24s %d', 'despesas sem campanha', $relatorio['orfaos']['despesas']));
        }

        $this->line('  apagadas de vez (inventadas): '.($relatorio['apagadas'] === []
            ? 'nenhuma'
            : implode(', ', array_map(fn ($id) => "#{$id}", $relatorio['apagadas']))));

        $this->line('  arquivadas (soft delete): '.($relatorio['arquivadas'] === []
            ? 'nenhuma'
            : implode(', ', array_map(fn ($id) => "#{$id}", $relatorio['arquivadas']))));

        foreach ($relatorio['ocupadas'] as $id => $restantes) {
            $detalhe = collect($restantes)->filter()->map(fn ($n, $t) => "{$t}={$n}")->implode(', ');
            $this->warn("  campanha #{$id} não foi arquivada: ainda tem registos ({$detalhe}).");
        }

        $this->newLine();
        $this->comment('Confirme o resultado em /campanhas e corra `php artisan optimize:clear`.');

        return self::SUCCESS;
    }

    /**
     * Campanha que a aplicacao criou sozinha, sem ninguem a pedir.
     *
     * Ate 18/09/2026 o OperacaoManagementController criava uma campanha sempre
     * que se gravava uma operacao numa cultura que ainda nao tivesse nenhuma.
     * Ficavam com a marca de fabrica: sem nome proprio (o nome era derivado da
     * cultura), agarradas a uma cultura, e sem data de fim. As campanhas
     * criadas de proposito — as gerais do agri:migrar-campanhas — tem nome e
     * nao tem cultura.
     *
     * Estas nunca foram nada, por isso podem ser apagadas em vez de
     * arquivadas. Na duvida, o metodo diz que nao.
     */
    private function foiInventada(Campanha $campanha): bool
    {
        return blank($campanha->nome)
            && $campanha->cultura_id !== null
            && $campanha->data_fim === null;
    }

    private function destinoDaCampanha(Campanha $campanha): string
    {
        if (! (bool) $this->option('apagar')) {
            return 'arquivada';
        }

        return $this->foiInventada($campanha)
            ? '<fg=red>inventada pela aplicação: APAGADA</>'
            : 'arquivada';
    }

    /**
     * As campanhas que tocam a epoca, ou as que forem indicadas a mao.
     *
     * @return \Illuminate\Support\Collection<int, Campanha>
     */
    private function campanhasAAbsorver(string $inicio, string $fim, ?Campanha $destino)
    {
        $indicadas = array_filter(array_map(
            'trim',
            explode(',', (string) $this->option('campanhas'))
        ));

        $query = Campanha::query()->with('cultura');

        if ($indicadas !== []) {
            $query->whereIn('id', $indicadas);
        } else {
            // Toca a epoca: comeca antes de ela acabar e ainda nao tinha
            // acabado quando ela comecou. Uma campanha sem fim (a "Cimeira 1")
            // conta como aberta.
            $query->whereDate('data_inicio', '<=', $fim)
                ->where(fn ($q) => $q->whereNull('data_fim')->orWhereDate('data_fim', '>=', $inicio));
        }

        if ($destino !== null) {
            $query->whereKeyNot($destino->id);
        }

        return $query->orderBy('id')->get();
    }

    /**
     * Tabelas com coluna campanha_id que se repontam com um UPDATE.
     *
     * Lida da base e nao de uma lista escrita a mao: ha tabelas que ganharam a
     * coluna depois do agri:migrar-campanhas e ficariam esquecidas.
     *
     * @return array<int, string>
     */
    private function tabelasComCampanha(): array
    {
        try {
            $todas = array_map(
                // O nome pode vir qualificado com o schema ("agro.custos"), e
                // a comparacao com 'campanha_parcela' passava ao lado.
                fn ($tabela) => Str::afterLast(
                    is_array($tabela) ? ($tabela['name'] ?? '') : (string) $tabela,
                    '.'
                ),
                Schema::getTableListing()
            );
        } catch (Throwable) {
            $todas = [];
        }

        if ($todas === []) {
            $todas = ['operacoes', 'custos', 'colheitas', 'receitas', 'despesas', 'compromissos'];
        }

        return array_values(array_filter(
            $todas,
            fn (string $tabela) => $tabela !== ''
                && ! in_array($tabela, self::TABELAS_ESPECIAIS, true)
                && $tabela !== 'campanhas'
                && Schema::hasColumn($tabela, 'campanha_id')
        ));
    }

    /**
     * @param  array<int, string>  $tabelas
     * @param  array<int, int>  $campanhaIds
     * @return array<string, int>
     */
    private function contarPorTabela(array $tabelas, array $campanhaIds): array
    {
        if ($campanhaIds === []) {
            return [];
        }

        $contagens = [];

        foreach ($tabelas as $tabela) {
            $total = DB::table($tabela)->whereIn('campanha_id', $campanhaIds)->count();

            if ($total > 0) {
                $contagens[$tabela] = $total;
            }
        }

        $ligacoes = DB::table('campanha_parcela')->whereIn('campanha_id', $campanhaIds)->count();

        if ($ligacoes > 0) {
            $contagens['campanha_parcela'] = $ligacoes;
        }

        return $contagens;
    }

    /**
     * Parcelas cobertas pelas campanhas a absorver: as ligadas explicitamente e
     * as que vem da cultura, nas campanhas antigas por parcela.
     *
     * @param  \Illuminate\Support\Collection<int, Campanha>  $campanhas
     * @return array<int, int>
     */
    private function parcelasDas($campanhas): array
    {
        $ligadas = DB::table('campanha_parcela')
            ->whereIn('campanha_id', $campanhas->pluck('id'))
            ->pluck('parcela_id');

        $dasCulturas = $campanhas
            ->map(fn (Campanha $campanha) => $campanha->cultura?->parcela_id)
            ->filter();

        return $ligadas->merge($dasCulturas)->unique()->values()->all();
    }

    /**
     * Custos partilhados e despesas gerais da epoca que ficaram sem campanha.
     *
     * Rateavel e a marca do que nao pertence a ninguem. Uma fatura de pecas
     * tambem fica sem campanha, mas nao e um gasto geral: pertence aquele
     * tractor ou aquela alfaia, e por isso fica de fora deste varrimento.
     *
     * @return \Illuminate\Database\Eloquent\Builder<Custo>|\Illuminate\Database\Eloquent\Builder<Despesa>
     */
    private function custosPartilhadosDaEpoca(string $inicio, string $fim)
    {
        return Custo::query()
            ->whereNull('campanha_id')
            ->where('rateavel', true)
            ->whereBetween('data_custo', [$inicio, $fim]);
    }

    private function despesasGeraisDaEpoca(string $inicio, string $fim)
    {
        return Despesa::query()
            ->whereNull('campanha_id')
            ->whereNull('maquina_id')
            ->whereNull('alfaia_id')
            ->whereBetween('data', [$inicio, $fim]);
    }
}
