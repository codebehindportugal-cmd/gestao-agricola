<?php

namespace App\Services\PaperInvoice;

/**
 * Le uma fatura: primeiro o OCR, e o modelo de visao quando o OCR nao chega.
 *
 * O OCR nao custa nada e resolve digitalizacoes e PDFs com texto. As fotos de
 * papel amarrotado sao outra coisa: o OCR devolve ruido, e e ai - e so ai -
 * que se gasta uma chamada a API.
 */
class LeituraFatura
{
    public function __construct(
        private readonly PaperInvoiceExtractor $ocr,
        private readonly LeitorFaturaClaude $visao,
    ) {
    }

    public function ler(string $caminho): array
    {
        $dados = $this->ocr->extract($caminho);

        if (! $this->precisaDeAjuda($dados)) {
            return $dados + ['fonte' => 'ocr'];
        }

        if (! $this->visao->disponivel()) {
            $dados['warnings'][] = 'O OCR nao conseguiu ler as linhas. Configure ANTHROPIC_API_KEY para a leitura assistida.';

            return $dados + ['fonte' => 'ocr'];
        }

        $lido = $this->visao->ler($caminho);

        if ($lido === null) {
            $dados['warnings'][] = 'A leitura assistida falhou; ficam apenas os dados do OCR.';

            return $dados + ['fonte' => 'ocr'];
        }

        return $this->juntar($dados, $lido);
    }

    /** O OCR chegou? Sem linhas, ou com linhas que nao somam o total, nao chegou. */
    private function precisaDeAjuda(array $dados): bool
    {
        if (($dados['products'] ?? []) === []) {
            return true;
        }

        $total = (float) ($dados['invoice']['total'] ?? 0);
        $somaLinhas = array_sum(array_column($dados['products'], 'lineTotal'));

        return $total > 0 && abs($total - $somaLinhas) > 0.05;
    }

    /**
     * O QR e a unica fonte exacta do numero, da data e do total: quando ele foi
     * lido, manda no cabecalho. O nome do fornecedor vem sempre do modelo - o
     * OCR costuma apanhar a linha da actividade ("Produtos para a agricultura,
     * Lda.") em vez do nome comercial.
     */
    private function juntar(array $ocr, array $visao): array
    {
        // O QR e a unica fonte exacta do numero, da data e do total. Quando foi
        // lido, manda no cabecalho; sem QR, vale o que o modelo leu na imagem.
        $temQr = filled($ocr['qrData'] ?? null);
        $cabecalho = $ocr['invoice'];

        foreach ($visao['invoice'] as $campo => $valor) {
            if ($this->vazio($valor)) {
                continue;
            }

            if (! $temQr || $this->vazio($cabecalho[$campo] ?? null)) {
                $cabecalho[$campo] = $valor;
            }
        }

        return [
            'source' => 'claude_vision',
            'fonte' => 'visao',
            // O nome do fornecedor vem do modelo: o OCR costuma apanhar a linha
            // da actividade ("Produtos para a agricultura, Lda.") e nao o nome.
            'supplier' => [
                'name' => $visao['supplier']['name'] ?: ($ocr['supplier']['name'] ?? ''),
                'taxNumber' => $ocr['supplier']['taxNumber'] ?: ($visao['supplier']['taxNumber'] ?? ''),
            ],
            'invoice' => $cabecalho,
            'products' => $visao['products'],
            'confidence' => 0.9,
            'needsManualReview' => true,
            'rawText' => $ocr['rawText'] ?? '',
            'qrData' => $ocr['qrData'] ?? null,
            'warnings' => array_values(array_filter(
                $ocr['warnings'] ?? [],
                fn (string $aviso) => ! str_contains($aviso, 'linhas de produtos')
                    && ! str_contains($aviso, 'nao coincide com o total')
                    && ! str_contains($aviso, 'OCR'),
            )),
        ];
    }

    /** Para o cabecalho, um zero e tao vazio como uma cadeia vazia. */
    private function vazio(mixed $valor): bool
    {
        return blank($valor) || (is_numeric($valor) && (float) $valor === 0.0);
    }
}
