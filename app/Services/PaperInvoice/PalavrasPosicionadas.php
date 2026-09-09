<?php

namespace App\Services\PaperInvoice;

/**
 * Converte as duas saidas locais em palavras com posicao, para o
 * TabelaFatura poder tratar um PDF e uma fotografia da mesma maneira.
 */
class PalavrasPosicionadas
{
    /**
     * `pdftotext -layout` mantem as colunas com espacos: a posicao da palavra
     * e' a coluna de caracteres em que comeca.
     *
     * @return array<int, array{texto: string, x: float, y: float, altura: float}>
     */
    public static function deTextoAlinhado(string $texto): array
    {
        $palavras = [];

        foreach (preg_split('/\R/u', $texto) ?: [] as $numeroLinha => $linha) {
            if (trim($linha) === '') {
                continue;
            }

            preg_match_all('/\S+/u', $linha, $encontradas, PREG_OFFSET_CAPTURE);

            foreach ($encontradas[0] as [$palavra, $posicao]) {
                $palavras[] = [
                    'texto' => $palavra,
                    // O offset vem em bytes; em colunas de caracteres com
                    // acentos isso desalinhava tudo.
                    'x' => (float) mb_strlen(substr($linha, 0, $posicao)),
                    'y' => (float) $numeroLinha,
                    'largura' => (float) mb_strlen($palavra),
                    'altura' => 1.0,
                ];
            }
        }

        return $palavras;
    }

    /**
     * Parte uma palavra colada pelos tracos da tabela, repartindo a largura
     * pelos pedacos para cada um ficar debaixo da sua coluna.
     *
     * @return array<int, array{texto: string, x: float, largura: float}>
     */
    private static function separarCelulas(string $texto, float $x, float $largura): array
    {
        $limpo = trim($texto, self::TRACOS);

        if ($limpo === '') {
            return [];
        }

        $pedacos = preg_split('/['.preg_quote(self::TRACOS, '/').']+/u', $limpo, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        if (count($pedacos) <= 1) {
            return [['texto' => $limpo, 'x' => $x, 'largura' => $largura]];
        }

        $caracteres = max(mb_strlen($limpo), 1);
        $porCaracter = $largura / $caracteres;
        $posicao = 0;
        $resultado = [];

        foreach ($pedacos as $pedaco) {
            $inicio = mb_strpos($limpo, $pedaco, $posicao);
            $inicio = $inicio === false ? $posicao : $inicio;

            $resultado[] = [
                'texto' => $pedaco,
                'x' => $x + $inicio * $porCaracter,
                'largura' => mb_strlen($pedaco) * $porCaracter,
            ];

            $posicao = $inicio + mb_strlen($pedaco);
        }

        return $resultado;
    }

    /**
     * Reconstroi o texto a partir do TSV, para nao ser preciso correr o
     * tesseract uma segunda vez so para obter as mesmas palavras.
     */
    public static function textoDeTsv(string $tsv): string
    {
        $linhas = [];

        foreach (preg_split('/\R/u', $tsv) ?: [] as $indice => $linha) {
            if ($indice === 0 || trim($linha) === '') {
                continue;
            }

            $campos = explode("\t", $linha);

            if (count($campos) < 12 || trim($campos[11]) === '') {
                continue;
            }

            // bloco/paragrafo/linha identificam a linha no documento.
            $chave = $campos[2].'-'.$campos[3].'-'.$campos[4];
            $linhas[$chave][] = trim($campos[11]);
        }

        return implode("\n", array_map(fn (array $palavras) => implode(' ', $palavras), $linhas));
    }

    /**
     * TSV do tesseract: uma palavra por linha, com caixa e confianca.
     *
     * @return array<int, array{texto: string, x: float, y: float, altura: float}>
     */
    /**
     * Caracteres que o tesseract inventa a partir dos traços da tabela. Colam
     * duas celulas numa palavra so ("V.UNITARIO|D1%") e faziam perder uma
     * coluna inteira.
     */
    private const TRACOS = '|[]_—–¦';

    public static function deTsv(string $tsv, float $confiancaMinima = 0): array
    {
        $palavras = [];

        foreach (preg_split('/\R/u', $tsv) ?: [] as $indice => $linha) {
            if ($indice === 0 || trim($linha) === '') {
                continue;
            }

            $campos = explode("\t", $linha);

            if (count($campos) < 12) {
                continue;
            }

            [$esquerda, $topo, , $altura, $confianca, $texto] = array_slice($campos, 6, 6);

            if (trim($texto) === '' || (float) $confianca < $confiancaMinima) {
                continue;
            }

            [$esquerda, $topo, $largura, $altura] = [(float) $esquerda, (float) $topo, (float) $campos[8], (float) $altura];

            foreach (self::separarCelulas(trim($texto), $esquerda, $largura) as $pedaco) {
                $palavras[] = [
                    'texto' => $pedaco['texto'],
                    'x' => $pedaco['x'],
                    // O meio da palavra aguenta melhor uma folha inclinada do que o topo.
                    'y' => $topo + $altura / 2,
                    'largura' => $pedaco['largura'],
                    'altura' => $altura,
                ];
            }
        }

        return $palavras;
    }
}
