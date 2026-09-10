<?php

namespace App\Services\PaperInvoice;

/**
 * Adivinha a categoria da despesa a partir do fornecedor e das linhas.
 *
 * E' um campo obrigatorio que ficava sempre em "outro" e depois obrigava a
 * corrigir a mao. O fornecedor decide quase sempre - a Casa Queridos vende
 * fitofarmacos e a AgroExclusive adubos - e, quando nao chega, as proprias
 * designacoes dizem o que e'.
 */
class CategoriaDaFatura
{
    /** Fornecedores cujo catálogo e' de um so tipo. */
    private const POR_FORNECEDOR = [
        'casa queridos' => 'fitofarmaceuticos',
        'agroexclusive' => 'fertilizantes',
        'agro exclusive' => 'fertilizantes',
    ];

    /** Palavra na designacao -> categoria. A ordem conta: a primeira que casar ganha. */
    private const POR_PALAVRA = [
        'fitofarmaceuticos' => ['fungicida', 'herbicida', 'inseticida', 'insecticida', 'acaricida', 'fitofarmac'],
        'fertilizantes' => ['adubo', 'fertilizante', 'foliar', 'nitrato', 'npk', 'ureia', 'sulfato', 'calcio', 'boro'],
        'combustivel' => ['gasoleo', 'gasóleo', 'gasolina', 'adblue', 'combustivel'],
        'sementes' => ['semente', 'sementes'],
        'pecas' => ['tubo', 'parafuso', 'filtro', 'rolamento', 'correia', 'oleo hidraulico', 'junta', 'mangueira', 'valvula'],
        'equipamento' => ['pulverizador', 'trator', 'tractor', 'alfaia', 'motor', 'bomba'],
    ];

    /**
     * @param  array<int, string>  $descricoes
     */
    public static function adivinhar(?string $fornecedor, array $descricoes): ?string
    {
        $numeroDgav = false;

        foreach ($descricoes as $descricao) {
            if (preg_match('/\bAV\s?-?\s?\d{3,6}\b/i', $descricao)) {
                $numeroDgav = true;
            }
        }

        // Um numero de autorizacao DGAV na linha nao deixa margem para duvida.
        if ($numeroDgav) {
            return 'fitofarmaceuticos';
        }

        $nome = self::normalizar((string) $fornecedor);

        foreach (self::POR_FORNECEDOR as $chave => $categoria) {
            if ($nome !== '' && str_contains($nome, self::normalizar($chave))) {
                return $categoria;
            }
        }

        $texto = self::normalizar(implode(' ', $descricoes));

        foreach (self::POR_PALAVRA as $categoria => $palavras) {
            foreach ($palavras as $palavra) {
                if (str_contains($texto, self::normalizar($palavra))) {
                    return $categoria;
                }
            }
        }

        return null;
    }

    private static function normalizar(string $texto): string
    {
        $texto = mb_strtolower(trim($texto));

        return strtr($texto, [
            'á' => 'a', 'à' => 'a', 'ã' => 'a', 'â' => 'a', 'é' => 'e', 'ê' => 'e',
            'í' => 'i', 'ó' => 'o', 'õ' => 'o', 'ô' => 'o', 'ú' => 'u', 'ç' => 'c',
        ]);
    }
}
