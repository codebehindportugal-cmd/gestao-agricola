<?php

namespace App\Services\Moloni;

/**
 * A especie de um artigo vendido, pelo nome que tem no Moloni.
 *
 * "Pera Rocha cal. 60/65" e Pereira; "Maçã Gala", "Golden", "Fuji" sao
 * Macieira. O que nao se reconhece (caixas, transporte, servicos) fica sem
 * especie e entra como venda "outro".
 *
 * Os nomes devolvidos sao os mesmos que as culturas usam na coluna `tipo`,
 * para o resumo por especie juntar a venda a linha certa.
 */
class EspecieDoArtigo
{
    /** @var array<string, array<int, string>> especie => palavras (sem acentos, minusculas) */
    private const PALAVRAS = [
        'Pereira' => ['pera', 'peras', 'pereira', 'rocha', 'carapinheira', 'conference', 'williams', 'passe crassane'],
        'Macieira' => [
            'maca', 'macas', 'macieira', 'gala', 'galas', 'fuji', 'golden', 'goldes', 'jonagold', 'jonagored',
            'reineta', 'granny', 'pink lady', 'starking', 'red delicious', 'bravo de esmolfe', 'esmolfe', 'royal',
        ],
        'Pessegueiro' => ['pessego', 'pessegos', 'nectarina', 'nectarinas'],
        'Ameixeira' => ['ameixa', 'ameixas', 'rainha claudia'],
        'Damasqueiro' => ['damasco', 'damascos'],
    ];

    public static function deNome(?string $nome): ?string
    {
        $texto = ' '.self::normalizar((string) $nome).' ';

        // "Pera" ganha a "Rocha"; mas "rocha" sozinho ("Pera Rocha") ja e pera.
        foreach (self::PALAVRAS as $especie => $palavras) {
            foreach ($palavras as $palavra) {
                if (str_contains($texto, ' '.$palavra.' ')) {
                    return $especie;
                }
            }
        }

        return null;
    }

    private static function normalizar(string $valor): string
    {
        $texto = mb_strtolower(trim($valor), 'UTF-8');
        $texto = strtr($texto, [
            'á' => 'a', 'à' => 'a', 'â' => 'a', 'ã' => 'a',
            'é' => 'e', 'ê' => 'e', 'í' => 'i',
            'ó' => 'o', 'ô' => 'o', 'õ' => 'o',
            'ú' => 'u', 'ç' => 'c',
        ]);

        // Pontuacao vira espaco: "Maçã-Gala", "Pera/Rocha", "Gala(cal.70)".
        $texto = (string) preg_replace('/[^a-z0-9]+/u', ' ', $texto);

        return trim((string) preg_replace('/\s+/', ' ', $texto));
    }
}
