<?php

namespace App\Services\PaperInvoice;

/**
 * Le o conteudo da embalagem a partir da designacao do artigo.
 *
 * "BANJO fluziname - 5 LT" comprado 2 vezes nao sao 2 unidades de stock: sao
 * 10 litros. A fatura conta embalagens, o campo conta produto. Sem esta
 * conversao o stock fica sempre errado e o custo por litro tambem.
 *
 * Nao e' preciso nada de inteligente: o tamanho vem escrito na designacao, e
 * os fornecedores escrevem-no sempre da mesma maneira.
 */
class TamanhoEmbalagem
{
    /** Unidade normalizada -> como se escreve na fatura. */
    private const UNIDADES = [
        'L' => ['l', 'lt', 'lts', 'litro', 'litros'],
        'ml' => ['ml', 'mls'],
        'kg' => ['kg', 'kgs', 'quilo', 'quilos'],
        'g' => ['g', 'gr', 'grs', 'grama', 'gramas'],
        'un' => ['un', 'uni', 'und', 'unid', 'unidade', 'unidades'],
        'doses' => ['dose', 'doses'],
    ];

    /**
     * @return array{conteudo: float, unidade: string}|null null quando a
     *                                                      designacao nao diz o tamanho
     */
    public static function daDescricao(string $descricao): ?array
    {
        $texto = mb_strtolower($descricao);
        $palavras = implode('|', array_merge(...array_values(self::UNIDADES)));

        // O ultimo tamanho da designacao e' o da embalagem: em "BANJO 5 LT
        // (AV 1199)" o que interessa e' o 5 LT, e um "20-20-20" de um adubo
        // nao tem unidade a seguir, por isso nao entra.
        if (! preg_match_all(
            '/(?<valor>\d+(?:[.,]\d+)?)\s*(?<unidade>'.$palavras.')\b/u',
            $texto,
            $encontrados,
            PREG_SET_ORDER
        )) {
            return null;
        }

        $ultimo = end($encontrados);
        $conteudo = (float) str_replace(',', '.', $ultimo['valor']);

        if ($conteudo <= 0) {
            return null;
        }

        return [
            'conteudo' => $conteudo,
            'unidade' => self::normalizarUnidade($ultimo['unidade']),
        ];
    }

    /** Mililitros e gramas guardam-se em litros e quilos, para o stock nao ficar em duas escalas. */
    public static function paraUnidadeBase(float $conteudo, string $unidade): array
    {
        return match ($unidade) {
            'ml' => ['conteudo' => round($conteudo / 1000, 4), 'unidade' => 'L'],
            'g' => ['conteudo' => round($conteudo / 1000, 4), 'unidade' => 'kg'],
            default => ['conteudo' => $conteudo, 'unidade' => $unidade],
        };
    }

    private static function normalizarUnidade(string $escrita): string
    {
        foreach (self::UNIDADES as $normalizada => $formas) {
            if (in_array($escrita, $formas, true)) {
                return $normalizada;
            }
        }

        return 'un';
    }
}
