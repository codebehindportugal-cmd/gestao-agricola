<?php

namespace App\Services\Moloni;

use App\Models\Campanha;
use App\Models\Receita;
use Carbon\CarbonImmutable;

/**
 * Importa as vendas do Moloni para `receitas`, para nao ser preciso
 * registar a mao o que ja esta faturado.
 *
 * - Documentos: faturas (FT), faturas-recibo (FR) e faturas simplificadas
 *   (FS), so os fechados (status 1). Notas de credito ficam de fora.
 * - Uma receita por linha do documento: quantidade, preco liquido de
 *   descontos (o da linha e o global do documento), sem IVA.
 * - A especie sai do nome do artigo (EspecieDoArtigo). Linhas que nao sao
 *   fruta entram como tipo "outro".
 * - Idempotente: referencia_externa "moloni:{tipo}:{document_id}:{n}". Correr
 *   outra vez so acrescenta o que faltar; o que ja entrou nao e alterado.
 */
class ImportadorVendasMoloni
{
    /** metodo da API => prefixo do documento */
    public const TIPOS = [
        'invoices' => 'FT',
        'invoiceReceipts' => 'FR',
        'simplifiedInvoices' => 'FS',
    ];

    private const POR_PAGINA = 50;

    public function __construct(private readonly MoloniCliente $moloni)
    {
    }

    /**
     * @return array{de:string, ate:string, documentos:int, criadas:int, existentes:int, sem_especie:int, valor:float, kg:float, avisos:array<int,string>}
     */
    public function importar(Campanha $campanha, ?CarbonImmutable $de = null, ?CarbonImmutable $ate = null): array
    {
        $de ??= CarbonImmutable::parse($campanha->data_inicio ?? now()->startOfYear());
        $ate ??= CarbonImmutable::parse($campanha->data_fim ?? now());
        if ($ate->isFuture()) {
            $ate = CarbonImmutable::today();
        }

        $companyId = $this->moloni->companyId();
        $resumo = [
            'de' => $de->toDateString(),
            'ate' => $ate->toDateString(),
            'documentos' => 0,
            'criadas' => 0,
            'existentes' => 0,
            'sem_especie' => 0,
            'valor' => 0.0,
            'kg' => 0.0,
            'avisos' => [],
        ];

        foreach (self::TIPOS as $metodo => $prefixo) {
            foreach ($this->documentos($metodo, $companyId, $de, $ate) as $cabecalho) {
                $resumo['documentos']++;
                $documento = $this->moloni->chamar("{$metodo}/getOne", [
                    'company_id' => $companyId,
                    'document_id' => $cabecalho['document_id'],
                ]);

                $this->importarDocumento($campanha, $metodo, $prefixo, $documento + $cabecalho, $resumo);
            }
        }

        $resumo['valor'] = round($resumo['valor'], 2);
        $resumo['kg'] = round($resumo['kg'], 2);

        return $resumo;
    }

    /**
     * Cabecalhos dos documentos fechados dentro do periodo.
     *
     * @return \Generator<int, array<string, mixed>>
     */
    private function documentos(string $metodo, int $companyId, CarbonImmutable $de, CarbonImmutable $ate): \Generator
    {
        for ($ano = $de->year; $ano <= $ate->year; $ano++) {
            $offset = 0;

            do {
                $pagina = $this->moloni->chamar("{$metodo}/getAll", [
                    'company_id' => $companyId,
                    'year' => $ano,
                    'qty' => self::POR_PAGINA,
                    'offset' => $offset,
                ]);

                foreach ($pagina as $documento) {
                    if (! is_array($documento) || empty($documento['document_id'])) {
                        continue;
                    }

                    // 1 = fechado. Rascunhos (0) e anulados nao sao vendas.
                    if ((int) ($documento['status'] ?? 1) !== 1) {
                        continue;
                    }

                    $data = $this->data($documento['date'] ?? null);
                    if ($data === null || $data->lt($de) || $data->gt($ate)) {
                        continue;
                    }

                    yield $documento;
                }

                $offset += self::POR_PAGINA;
            } while (count($pagina) >= self::POR_PAGINA && $offset < 20000);
        }
    }

    private function importarDocumento(Campanha $campanha, string $metodo, string $prefixo, array $documento, array &$resumo): void
    {
        $linhas = $documento['products'] ?? [];
        $numero = $this->numeroDocumento($prefixo, $documento);
        $data = $this->data($documento['date'] ?? null)?->toDateString();
        $descontoGlobal = (float) ($documento['global_discount'] ?? 0);

        if (! is_array($linhas) || $linhas === []) {
            $resumo['avisos'][] = "{$numero}: sem linhas de artigos, nao importado.";

            return;
        }

        foreach (array_values($linhas) as $indice => $linha) {
            $referencia = "moloni:{$metodo}:{$documento['document_id']}:".($indice + 1);

            if (Receita::withTrashed()->where('referencia_externa', $referencia)->exists()) {
                $resumo['existentes']++;

                continue;
            }

            $quantidade = (float) ($linha['qty'] ?? 0);
            $preco = (float) ($linha['price'] ?? 0);
            $fator = (1 - (float) ($linha['discount'] ?? 0) / 100) * (1 - $descontoGlobal / 100);
            $valor = round($quantidade * $preco * $fator, 2);

            if ($valor <= 0) {
                continue;
            }

            $nome = trim((string) ($linha['name'] ?? 'Artigo'));
            $especie = EspecieDoArtigo::deNome($nome.' '.($linha['summary'] ?? ''));

            if ($especie === null) {
                $resumo['sem_especie']++;
            }

            $unidade = $this->unidade($linha);

            Receita::query()->create([
                'descricao' => mb_substr($nome, 0, 255),
                'tipo' => $especie ? 'venda_colheita' : 'outro',
                'valor' => $valor,
                'quantidade' => $quantidade > 0 ? $quantidade : null,
                'unidade' => $unidade,
                'preco_unitario' => $quantidade > 0 ? round($valor / $quantidade, 4) : null,
                'data' => $data,
                'campanha_id' => $campanha->id,
                'especie' => $especie,
                'comprador_nome' => $documento['entity_name'] ?? null,
                'documento' => $numero,
                'referencia_externa' => $referencia,
                'observacoes' => 'Importado do Moloni',
            ]);

            $resumo['criadas']++;
            $resumo['valor'] += $valor;
            if ($unidade === 'kg') {
                $resumo['kg'] += $quantidade;
            }
        }
    }

    private function numeroDocumento(string $prefixo, array $documento): string
    {
        $serie = $documento['document_set_name'] ?? ($documento['document_set']['name'] ?? null);
        $numero = $documento['number'] ?? $documento['document_id'];

        return trim($prefixo.' '.($serie ? $serie.'/' : '').$numero);
    }

    /** A unidade da linha, quando o Moloni a devolve; senao kg (e fruta). */
    private function unidade(array $linha): string
    {
        $unidade = $linha['measurement_unit']['short_name']
            ?? $linha['unit_short_name']
            ?? $linha['product']['measurement_unit']['short_name']
            ?? null;

        $unidade = mb_strtolower(trim((string) $unidade));

        return match (true) {
            $unidade === '' , in_array($unidade, ['kg', 'kgs', 'kilo', 'quilo', 'quilos'], true) => 'kg',
            in_array($unidade, ['un', 'uni', 'und', 'unid', 'unidade', 'unidades'], true) => 'un',
            default => mb_substr($unidade, 0, 20),
        };
    }

    private function data(mixed $valor): ?CarbonImmutable
    {
        if (blank($valor)) {
            return null;
        }

        try {
            return CarbonImmutable::parse($valor)->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }
}
