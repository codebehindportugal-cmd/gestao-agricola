<?php

namespace App\Services\PaperInvoice;

use App\Models\Despesa;
use App\Models\FaturaItem;
use App\Models\Produto;

/**
 * Cria no catalogo os produtos que uma fatura traz, para o stock subir sozinho.
 *
 * O ecra so dava entrada em stock nas linhas ja ligadas a um produto; as
 * outras ficavam na despesa e o stock nao mexia. Quem carrega vinte faturas
 * por semana nao vai ligar linha a linha.
 *
 * So consumiveis: fitofarmacos, adubos, sementes e combustivel. Pecas,
 * equipamento e mao de obra ficam como despesa e nao entram no catalogo - a
 * 500 faturas por mes, cada parafuso viraria um artigo que nunca mais se
 * repete.
 */
class ProdutosDaFatura
{
    /** Categoria da despesa -> tipo do produto criado. As outras nao criam nada. */
    private const CONSUMIVEIS = [
        'fitofarmaceuticos' => Produto::TIPO_FITOFARMACO,
        'fertilizantes' => 'fertilizante',
        'sementes' => 'semente',
        'combustivel' => 'combustivel',
    ];

    /**
     * @return array{ligados: int, criados: array<int, string>, avisos: array<int, string>}
     */
    public function garantir(Despesa $despesa): array
    {
        $tipo = self::CONSUMIVEIS[$despesa->categoria] ?? null;
        $resultado = ['ligados' => 0, 'criados' => [], 'avisos' => []];

        if ($tipo === null) {
            $this->garantirEmbalagens($despesa, $resultado);

            return $resultado;
        }

        foreach ($despesa->items as $item) {
            if ($item->produto_id !== null) {
                continue;
            }

            $existente = $this->existente($item);

            if ($existente !== null) {
                $item->update(['produto_id' => $existente->id]);
                $resultado['ligados']++;

                continue;
            }

            $dgav = $this->numeroDgav($item->descricao);

            // Conformidade DGAV: um fitofarmaco sem numero de autorizacao nao
            // pode entrar no catalogo, nem sequer em silencio.
            if ($tipo === Produto::TIPO_FITOFARMACO && $dgav === null) {
                $resultado['avisos'][] = "\"{$item->descricao}\" não entrou em stock: falta o número de autorização DGAV.";

                continue;
            }

            $produto = $this->criar($item, $tipo, $dgav);
            $item->update(['produto_id' => $produto->id]);
            $resultado['criados'][] = $produto->nome;
        }

        $despesa->load('items.produto');
        $this->garantirEmbalagens($despesa, $resultado);

        return $resultado;
    }

    /**
     * Grava em cada linha quanto leva a embalagem, lido da designacao.
     *
     * E o que faz "2 x ERUNE 5 LT" dar 10 litros mesmo quando o produto ja
     * existia no catalogo com conteudo 1 — o caso que entrou mal. O tamanho
     * fica na linha, por isso o movimento e reproduzivel; e se o produto estava
     * sem tamanho, aproveita-se para o corrigir.
     *
     * @param  array{ligados: int, criados: array<int, string>, avisos: array<int, string>}  $resultado
     */
    private function garantirEmbalagens(Despesa $despesa, array &$resultado): void
    {
        foreach ($despesa->items as $item) {
            $lido = TamanhoEmbalagem::daDescricao($item->descricao);

            if ($lido === null) {
                continue;
            }

            $base = TamanhoEmbalagem::paraUnidadeBase($lido['conteudo'], $lido['unidade']);

            if ((float) ($item->conteudo_embalagem ?? 0) <= 0) {
                $item->update([
                    'conteudo_embalagem' => $base['conteudo'],
                    'unidade_embalagem' => $base['unidade'],
                ]);
            }

            $produto = $item->produto;

            if ($produto === null) {
                continue;
            }

            $doProduto = (float) ($produto->conteudo ?: 0);

            if (abs($doProduto - $base['conteudo']) < 0.0001) {
                continue;
            }

            // Conteudo a 1 ou vazio e o valor por omissao de antes desta coluna
            // existir: corrige-se. Com outro tamanho a serio nao se mexe — pode
            // ser a embalagem grande que ele costuma comprar — e avisa-se.
            if ($doProduto <= 1) {
                $produto->update(array_filter([
                    'conteudo' => $base['conteudo'],
                    'unidade_medida' => blank($produto->unidade_medida) ? $base['unidade'] : null,
                ], fn ($valor) => $valor !== null));

                continue;
            }

            $resultado['avisos'][] = sprintf(
                'O conteúdo de "%s" no catálogo (%s %s) difere da fatura (%s %s); a entrada usou o da fatura.',
                $produto->nome,
                rtrim(rtrim(number_format($doProduto, 4, ',', ''), '0'), ','),
                $produto->unidade_medida ?? '',
                rtrim(rtrim(number_format($base['conteudo'], 4, ',', ''), '0'), ','),
                $base['unidade']
            );
        }
    }

    /** Produto que ja exista com este nome, para nao duplicar o catalogo. */
    private function existente(FaturaItem $item): ?Produto
    {
        $nome = trim($item->descricao);

        return Produto::query()
            ->whereRaw('LOWER(nome) = ?', [mb_strtolower($nome)])
            ->first();
    }

    private function criar(FaturaItem $item, string $tipo, ?string $dgav): Produto
    {
        $embalagem = TamanhoEmbalagem::daDescricao($item->descricao);
        $embalagem = $embalagem === null
            ? null
            : TamanhoEmbalagem::paraUnidadeBase($embalagem['conteudo'], $embalagem['unidade']);

        return Produto::query()->create([
            'nome' => mb_substr(trim($item->descricao), 0, 255),
            'tipo' => $tipo,
            'numero_autorizacao_dgav' => $dgav,
            'unidade_medida' => $embalagem['unidade'] ?? 'un',
            'conteudo' => $embalagem['conteudo'] ?? null,
            // O custo do catalogo e por unidade de stock: um bidao de 5 L a
            // 164,90 sao 32,98 por litro.
            'custo_unitario' => $embalagem === null
                ? (float) $item->preco_liquido
                : round((float) $item->preco_liquido / $embalagem['conteudo'], 4),
        ]);
    }

    /** "ERUNE primetanil - 5 LT ( AV 1761 )" -> "AV 1761". */
    private function numeroDgav(string $descricao): ?string
    {
        return preg_match('/\bAV\s?-?\s?(\d{3,6})\b/i', $descricao, $partes)
            ? 'AV '.$partes[1]
            : null;
    }
}
