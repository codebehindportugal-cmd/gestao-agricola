<?php

namespace App\Services\PaperInvoice;

/**
 * Le a tabela de artigos de uma fatura a partir de palavras posicionadas.
 *
 * A leitura por expressoes regulares assume que a linha vem inteira e pela
 * ordem certa. Numa fatura isso e' falso: as colunas tem posicoes fixas, o
 * OCR troca a ordem das palavras e as colunas vazias (D1%, D2%) desaparecem
 * do texto sem deixar rasto - e a partir dai todos os numeros escorregam uma
 * casa. Aqui trabalha-se com coordenadas: le-se o cabecalho da tabela, ficam
 * definidas as fronteiras das colunas, e cada palavra cai na coluna onde
 * esta. Uma coluna vazia continua vazia, em vez de puxar a seguinte.
 *
 * Serve as duas origens sem saber a diferenca:
 *   - `pdftotext -layout` (PDF com texto): x = coluna de caracteres;
 *   - `tesseract ... tsv` (foto): x = pixeis.
 */
class TabelaFatura
{
    /** Sinonimos de cabecalho, ja sem acentos e em minusculas. */
    private const COLUNAS = [
        'descricao' => ['descricao', 'designacao', 'artigo', 'produto', 'servico', 'referencia'],
        'quantidade' => ['qt', 'qtd', 'qtde', 'quant', 'quantidade'],
        'preco' => ['unitario', 'vunitario', 'preco', 'punit', 'precounit'],
        // Sem "desc" solto: apanhava "descarga" do cabecalho da guia.
        'desconto' => ['d1', 'd2', 'desconto', 'dto'],
        'iva' => ['iva', 'taxa'],
        'total' => ['total', 'valor'],
    ];

    /** Linhas a partir das quais a tabela de artigos acabou. */
    private const FIM_DA_TABELA = [
        'totalilquido', 'totaliliquido', 'totaldocumento', 'totalapagar', 'apagar',
        'descontossiva', 'liquidosiva', 'subtotal', 'totais', 'atcud', 'incidencia',
    ];

    /**
     * @param  array<int, array{texto: string, x: float, y: float, altura: float}>  $palavras
     * @return array<int, array{description: string, quantity: float, unitPrice: float, discountRate: float, vatRate: float, lineTotal: float, confidence: float}>
     */
    public function linhas(array $palavras): array
    {
        $filas = $this->filas($palavras);
        $cabecalho = $this->encontrarCabecalho($filas);

        if ($cabecalho === null) {
            return [];
        }

        [$indiceCabecalho, $colunas] = $cabecalho;
        $artigos = [];

        foreach (array_slice($filas, $indiceCabecalho + 1) as $fila) {
            if ($this->fimDaTabela($fila)) {
                break;
            }

            $artigo = $this->artigo($fila, $colunas);

            if ($artigo !== null) {
                $artigos[] = $artigo;
            }
        }

        return $artigos;
    }

    /**
     * Agrupa palavras em filas pela altura a que estao.
     *
     * @param  array<int, array<string, mixed>>  $palavras
     * @return array<int, array<int, array<string, mixed>>>
     */
    private function filas(array $palavras): array
    {
        if ($palavras === []) {
            return [];
        }

        usort($palavras, fn ($a, $b) => $a['y'] <=> $b['y']);

        $filas = [];
        $atual = [];
        $topo = 0.0;
        $base = 0.0;

        foreach ($palavras as $palavra) {
            $altura = max($palavra['altura'], 0.001);
            $palavraTopo = $palavra['y'] - $altura / 2;
            $palavraBase = $palavra['y'] + $altura / 2;

            // Duas palavras estao na mesma linha quando se sobrepoem na
            // vertical. Comparar so o centro parte a linha ao meio assim que a
            // folha esta um grau torta - foi o que aconteceu com a fatura da
            // Casa Queridos, em que o preco e o total ficavam de fora da linha
            // do artigo.
            $sobreposicao = min($base, $palavraBase) - max($topo, $palavraTopo);

            if ($atual !== [] && $sobreposicao >= $altura * 0.35) {
                $atual[] = $palavra;
                $topo = min($topo, $palavraTopo);
                $base = max($base, $palavraBase);

                continue;
            }

            if ($atual !== []) {
                $filas[] = $this->ordenarPorX($atual);
            }

            $atual = [$palavra];
            $topo = $palavraTopo;
            $base = $palavraBase;
        }

        if ($atual !== []) {
            $filas[] = $this->ordenarPorX($atual);
        }

        return $filas;
    }

    private function ordenarPorX(array $fila): array
    {
        usort($fila, fn ($a, $b) => $a['x'] <=> $b['x']);

        return $fila;
    }

    /**
     * A fila do cabecalho e as fronteiras das colunas que dela saem.
     *
     * @return array{0: int, 1: array<int, array{campo: string, inicio: float, fim: float}>}|null
     */
    private function encontrarCabecalho(array $filas): ?array
    {
        foreach ($filas as $indice => $fila) {
            // O cabecalho de uma tabela vem muitas vezes em duas alturas
            // ligeiramente diferentes ("DESCRIÇÃO" mais acima, "V.UNITÁRIO"
            // mais abaixo). Numa foto isso chega para o partir em duas filas,
            // e ai nenhuma delas sozinha parece um cabecalho.
            foreach ($this->candidatosACabecalho($filas, $indice) as $candidato) {
                $colunas = $this->colunasDoCabecalho($candidato);

                if ($colunas !== null) {
                    return [$indice + count($candidato['filas']) - 1, $colunas];
                }
            }
        }

        return null;
    }

    /**
     * A fila sozinha e, a seguir, a fila com a de baixo.
     *
     * @return array<int, array{palavras: array, filas: array}>
     */
    private function candidatosACabecalho(array $filas, int $indice): array
    {
        $candidatos = [['palavras' => $filas[$indice], 'filas' => [$indice]]];

        // So se junta a fila de baixo quando esta ja tem alguma coluna: juntar
        // uma fila sem colunas nenhumas so traz palavras de outra parte da
        // folha, e sao elas a definir fronteiras onde nao ha colunas.
        if (isset($filas[$indice + 1]) && $this->temAlgumaColuna($filas[$indice])) {
            $candidatos[] = [
                'palavras' => array_merge($filas[$indice], $filas[$indice + 1]),
                'filas' => [$indice, $indice + 1],
            ];
        }

        return $candidatos;
    }

    private function temAlgumaColuna(array $fila): bool
    {
        foreach ($fila as $palavra) {
            if ($this->campoDoCabecalho($palavra['texto']) !== null) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<int, array{campo: string, inicio: float, fim: float}>|null
     */
    private function colunasDoCabecalho(array $candidato): ?array
    {
        {
            $fila = $candidato['palavras'];
            $marcadas = [];

            // Todas as palavras do cabecalho entram, mesmo as que nao
            // interessam (CODIGO, LOTE): sem elas, a coluna do lote acabava
            // dentro da quantidade e o numero do lote virava a quantidade.
            foreach ($fila as $palavra) {
                $marcadas[] = [
                    'campo' => $this->campoDoCabecalho($palavra['texto']),
                    'x' => $palavra['x'],
                    'largura' => $palavra['largura'] ?? 1.0,
                ];
            }

            $campos = array_values(array_unique(array_filter(array_column($marcadas, 'campo'))));

            // Precisa da descricao e de pelo menos duas colunas de numeros:
            // menos do que isto e' uma frase qualquer com a palavra "total".
            if (! in_array('descricao', $campos, true) || count($campos) < 3) {
                return null;
            }

            return $this->fronteiras($marcadas);
        }
    }

    /**
     * Cada coluna vai do seu inicio ate ao inicio da seguinte. As colunas de
     * numeros sao alinhadas a direita, por isso o texto cai antes do titulo;
     * recua-se um pouco a fronteira para o apanhar.
     *
     * @return array<int, array{campo: string, inicio: float, fim: float}>
     */
    private function fronteiras(array $marcadas): array
    {
        usort($marcadas, fn ($a, $b) => $a['x'] <=> $b['x']);

        $colunas = [];
        $total = count($marcadas);

        foreach ($marcadas as $i => $marcada) {
            $anterior = $i === 0 ? null : $marcadas[$i - 1]['x'];
            $seguinte = $i === $total - 1 ? null : $marcadas[$i + 1]['x'];

            $inicio = $anterior === null ? -INF : ($anterior + $marcada['x']) / 2;
            $fim = $seguinte === null ? INF : ($marcada['x'] + $seguinte) / 2;

            if ($marcada['campo'] === null) {
                continue;
            }

            $colunas[] = ['campo' => $marcada['campo'], 'inicio' => $inicio, 'fim' => $fim];
        }

        return $this->garantirColunaDoTotal($colunas, $marcadas);
    }

    /**
     * Numa foto, o titulo "TOTAL" e' dos que mais se perdem - fica encostado
     * ao traco da direita. Mas a coluna esta la: e' tudo o que vem depois do
     * ultimo titulo lido. Sem isto, o total da linha caia na coluna anterior.
     */
    private function garantirColunaDoTotal(array $colunas, array $marcadas): array
    {
        if ($colunas === [] || in_array('total', array_column($colunas, 'campo'), true)) {
            return $colunas;
        }

        $ultima = array_key_last($colunas);
        $ultimoTitulo = end($marcadas);
        $fronteira = $ultimoTitulo['x'] + max($ultimoTitulo['largura'] ?? 0, 1) * 1.5;

        if ($colunas[$ultima]['inicio'] >= $fronteira) {
            return $colunas;
        }

        $colunas[$ultima]['fim'] = $fronteira;
        $colunas[] = ['campo' => 'total', 'inicio' => $fronteira, 'fim' => INF];

        return $colunas;
    }

    private function campoDoCabecalho(string $texto): ?string
    {
        $limpo = $this->normalizar($texto);

        if ($limpo === '') {
            return null;
        }

        // Por "contem" e nao por igualdade: o OCR devolve "descricao" colado a
        // tracos da tabela e "vunitario" com restos da celula ao lado.
        $melhor = null;

        foreach (self::COLUNAS as $campo => $sinonimos) {
            foreach ($sinonimos as $sinonimo) {
                $posicao = strpos($limpo, $sinonimo);

                if ($posicao === false) {
                    continue;
                }

                // Ganha o sinonimo que aparece mais cedo na palavra e, em
                // empate, o mais longo - "vunitario" vale mais que "unit".
                $peso = [$posicao, -strlen($sinonimo)];

                if ($melhor === null || $peso < $melhor['peso']) {
                    $melhor = ['campo' => $campo, 'peso' => $peso];
                }
            }
        }

        return $melhor['campo'] ?? null;
    }

    private function fimDaTabela(array $fila): bool
    {
        $junto = $this->normalizar(implode('', array_column($fila, 'texto')));

        foreach (self::FIM_DA_TABELA as $marca) {
            // Contem, e nao comeca por: numa foto a linha dos totais vem
            // muitas vezes colada a outra coisa qualquer da mesma altura.
            if (str_contains($junto, $marca)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<int, array{campo: string, inicio: float, fim: float}>  $colunas
     */
    private function artigo(array $fila, array $colunas): ?array
    {
        $porCampo = [];

        foreach ($fila as $palavra) {
            $campo = $this->campoDaPosicao($palavra['x'], $colunas);

            // Numeros a direita da ultima coluna conhecida sao o total: nas
            // fotos o cabecalho "TOTAL" perde-se com frequencia, mas a coluna
            // continua la.
            if ($campo === null && $this->parecaNumero($palavra['texto']) && $this->depoisDasColunas($palavra['x'], $colunas)) {
                $campo = 'total';
            }

            if ($campo !== null) {
                $porCampo[$campo][] = $palavra;
            }
        }

        $porCampo = array_map(fn (array $palavras) => $this->juntarNumerosPartidos($palavras), $porCampo);

        $descricao = trim(implode(' ', $porCampo['descricao'] ?? []));
        $descricao = trim(preg_replace('/\s+/u', ' ', $descricao) ?? $descricao);

        // Um artigo tem uma designacao, nao um paragrafo. Isto corta o rodape
        // legal da fatura, que de outra forma entrava como linha.
        if (mb_strlen($descricao) < 3 || str_word_count($descricao) > 12) {
            return null;
        }

        $quantidade = $this->numero($porCampo['quantidade'] ?? []) ?? 1.0;
        $preco = $this->numero($porCampo['preco'] ?? []);
        $total = $this->numero($porCampo['total'] ?? []);
        $iva = $this->numero($porCampo['iva'] ?? []) ?? 0.0;
        $desconto = $this->somaDescontos($porCampo['desconto'] ?? []);

        // Uma linha de artigo tem sempre dinheiro. Sem preco nem total e'
        // continuacao de descricao, ou lixo do OCR.
        if ($preco === null && $total === null) {
            return null;
        }

        // Numa foto, a coluna da quantidade e' das primeiras a perder-se. Com o
        // preco e o total, sai por divisao - e so se aceita se der um numero
        // limpo, para nao inventar 1,97 unidades.
        if (($porCampo['quantidade'] ?? []) === [] && $preco > 0 && $total !== null) {
            $calculada = $total / ($preco * (1 - $desconto / 100));

            if ($calculada > 0 && abs($calculada - round($calculada, 2)) < 0.005) {
                $quantidade = round($calculada, 2);
            }
        }

        $preco ??= $quantidade > 0 && $total !== null ? round($total / $quantidade, 4) : 0.0;
        $total ??= round($quantidade * $preco * (1 - $desconto / 100), 2);

        if ($total <= 0) {
            return null;
        }

        return [
            'description' => mb_substr($descricao, 0, 255),
            'quantity' => $quantidade,
            'unitPrice' => $preco,
            'discountRate' => $desconto,
            'vatRate' => $iva > 100 ? 0.0 : $iva,
            'lineTotal' => $total,
            'confidence' => $this->confianca($quantidade, $preco, $desconto, $total),
        ];
    }

    /**
     * O OCR perde o ponto decimal e devolve "329" e "800" como duas palavras
     * coladas. Se estao na mesma coluna e quase encostadas, era um numero so.
     *
     * @param  array<int, array<string, mixed>>  $palavras
     * @return array<int, string>
     */
    private function juntarNumerosPartidos(array $palavras): array
    {
        $juntas = [];

        foreach ($palavras as $palavra) {
            $anterior = $juntas === [] ? null : array_key_last($juntas);

            if ($anterior !== null
                && $this->parecaNumero($juntas[$anterior]['texto'])
                && $this->parecaNumero($palavra['texto'])
                && ! str_contains($juntas[$anterior]['texto'], '.')
                && ! str_contains($juntas[$anterior]['texto'], ',')) {

                $fim = $juntas[$anterior]['x'] + $juntas[$anterior]['largura'];
                $espaco = $palavra['x'] - $fim;
                $larguraCaracter = $palavra['largura'] / max(mb_strlen($palavra['texto']), 1);
                $casas = mb_strlen($palavra['texto']);

                if ($espaco < $larguraCaracter * 0.8 && $casas >= 1 && $casas <= 4) {
                    $juntas[$anterior]['texto'] .= '.'.$palavra['texto'];
                    $juntas[$anterior]['largura'] = $palavra['x'] + $palavra['largura'] - $juntas[$anterior]['x'];

                    continue;
                }
            }

            $juntas[] = $palavra;
        }

        return array_column($juntas, 'texto');
    }

    private function parecaNumero(string $texto): bool
    {
        return (bool) preg_match('/^[\d][\d.,]*$/u', trim($texto));
    }

    private function depoisDasColunas(float $x, array $colunas): bool
    {
        foreach ($colunas as $coluna) {
            if ($coluna['fim'] === INF) {
                // Ja ha uma coluna aberta a direita: nada fica de fora.
                return false;
            }
        }

        $ultimoFim = max(array_column($colunas, 'fim'));

        return $x >= $ultimoFim;
    }

    private function campoDaPosicao(float $x, array $colunas): ?string
    {
        foreach ($colunas as $coluna) {
            if ($x >= $coluna['inicio'] && $x < $coluna['fim']) {
                return $coluna['campo'];
            }
        }

        return null;
    }

    /** Duas colunas de desconto em cascata valem uma so percentagem efectiva. */
    private function somaDescontos(array $textos): float
    {
        $efectivo = 1.0;

        foreach ($textos as $texto) {
            $valor = $this->numero([$texto]);

            if ($valor !== null && $valor > 0 && $valor < 100) {
                $efectivo *= 1 - $valor / 100;
            }
        }

        return round((1 - $efectivo) * 100, 2);
    }

    /**
     * Numero como vem nas faturas: 1.234,56, 1 234.56, 103.5000, 329.800.
     * Devolve null quando nao ha algarismos - coluna vazia continua vazia.
     */
    private function numero(array $textos): ?float
    {
        // Uma coluna pode trazer lixo colado ("2.00" e um simbolo solto). Vale
        // o ultimo pedaco que parece mesmo um numero.
        // Sem espacos na classe: dois numeros seguidos na mesma coluna sao dois
        // numeros, e nao um so gigante.
        preg_match_all('/-?\d[\d.,]*\d|-?\d/u', implode(' ', $textos), $pedacos);

        $texto = trim((string) (end($pedacos[0]) ?: ''));
        $texto = preg_replace('/\s/u', '', $texto) ?? $texto;

        if ($texto === '' || ! preg_match('/\d/', $texto)) {
            return null;
        }

        $ultimaVirgula = strrpos($texto, ',');
        $ultimoPonto = strrpos($texto, '.');
        $decimal = max($ultimaVirgula === false ? -1 : $ultimaVirgula, $ultimoPonto === false ? -1 : $ultimoPonto);

        if ($decimal === -1) {
            return (float) $texto;
        }

        $casas = strlen($texto) - $decimal - 1;

        // Tres algarismos depois do separador sao decimais quando e' o unico
        // separador do numero (a Casa Queridos imprime 329.800 = 329,80); com
        // dois separadores, o primeiro e' dos milhares.
        $inteiro = preg_replace('/[.,]/', '', substr($texto, 0, $decimal)) ?? '';
        $fraccao = substr($texto, $decimal + 1);

        if ($casas === 3 && substr_count($texto, ',') + substr_count($texto, '.') > 1) {
            return (float) ($inteiro.$fraccao);
        }

        return (float) ($inteiro.'.'.$fraccao);
    }

    /** As contas da linha fecham? E' o unico juiz que nao depende de ler bem. */
    private function confianca(float $quantidade, float $preco, float $desconto, float $total): float
    {
        $esperado = $quantidade * $preco * (1 - $desconto / 100);

        if ($total <= 0 || $esperado <= 0) {
            return 0.4;
        }

        $desvio = abs($esperado - $total) / max($total, 0.01);

        return match (true) {
            $desvio <= 0.01 => 0.95,
            $desvio <= 0.05 => 0.7,
            default => 0.4,
        };
    }

    private function normalizar(string $texto): string
    {
        $texto = mb_strtolower(trim($texto));
        $texto = strtr($texto, [
            'á' => 'a', 'à' => 'a', 'ã' => 'a', 'â' => 'a', 'é' => 'e', 'ê' => 'e',
            'í' => 'i', 'ó' => 'o', 'õ' => 'o', 'ô' => 'o', 'ú' => 'u', 'ç' => 'c',
        ]);

        return preg_replace('/[^a-z0-9]/', '', $texto) ?? '';
    }
}
