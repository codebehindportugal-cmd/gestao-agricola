<?php

namespace App\Services\PaperInvoice;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Le uma fatura com o modelo de visao do Claude.
 *
 * O OCR le bem folhas direitas e digitalizadas; nao le uma foto de papel
 * amarrotado com marca de agua por cima, que e como as faturas chegam de
 * facto. Nessas, o tesseract devolve linhas como
 * "Fovisey [ERNE pew BL aw Te am iN ex] aaa" - nao ha padrao que as salve.
 *
 * Este leitor devolve exactamente a mesma forma que o PaperInvoiceExtractor,
 * para poder entrar no lugar dele sem o resto do codigo saber a diferenca.
 */
class LeitorFaturaClaude
{
    private const ENDPOINT = 'https://api.anthropic.com/v1/messages';

    private const VERSAO_API = '2023-06-01';

    /** O maior lado a que a imagem e reduzida antes de subir: acima disto so paga tokens. */
    private const LADO_MAXIMO = 1600;

    public function disponivel(): bool
    {
        return filled(config('paper_invoice.claude.api_key'));
    }

    /**
     * @return array{supplier: array, invoice: array, products: array}|null null quando nao deu para ler
     */
    public function ler(string $caminho): ?array
    {
        if (! $this->disponivel() || ! is_file($caminho)) {
            return null;
        }

        $bloco = $this->blocoDoFicheiro($caminho);

        if ($bloco === null) {
            return null;
        }

        try {
            $resposta = Http::withHeaders([
                'x-api-key' => config('paper_invoice.claude.api_key'),
                'anthropic-version' => self::VERSAO_API,
                'content-type' => 'application/json',
            ])
                ->timeout((int) config('paper_invoice.claude.timeout', 90))
                ->post(self::ENDPOINT, [
                    'model' => config('paper_invoice.claude.model'),
                    'max_tokens' => 4096,
                    'system' => $this->instrucoes(),
                    'messages' => [[
                        'role' => 'user',
                        'content' => [
                            $bloco,
                            ['type' => 'text', 'text' => 'Lê esta fatura e devolve o JSON.'],
                        ],
                    ]],
                ]);
        } catch (\Throwable $e) {
            Log::warning('Leitura da fatura pelo Claude falhou: '.$e->getMessage());

            return null;
        }

        if ($resposta->failed()) {
            Log::warning('Leitura da fatura pelo Claude devolveu '.$resposta->status().': '.$resposta->body());

            return null;
        }

        $texto = collect($resposta->json('content') ?? [])
            ->where('type', 'text')
            ->pluck('text')
            ->implode('');

        $dados = $this->descodificar($texto);

        return $dados === null ? null : $this->paraFormatoDoExtractor($dados);
    }

    private function instrucoes(): string
    {
        return <<<'TXT'
        És um leitor de faturas de compra portuguesas. Devolves SEMPRE e SÓ um objecto JSON, sem texto à volta e sem blocos de código.

        Formato:
        {
          "fornecedor": string|null,        // nome comercial de quem emitiu a fatura, não a morada nem a actividade
          "nif": string|null,               // contribuinte do fornecedor, 9 dígitos
          "numero_fatura": string|null,     // como aparece no documento, ex. "FT 1100/135909"
          "data": "AAAA-MM-DD"|null,        // data do documento, não a data de carga nem o vencimento
          "total": number|null,             // total a pagar, com IVA
          "total_iva": number|null,
          "linhas": [
            {
              "descricao": string,          // designação do artigo, sem o código nem o lote
              "quantidade": number,
              "preco_unitario": number,     // preço unitário sem IVA
              "iva_percentagem": number,    // 0, 6, 13 ou 23
              "total_linha": number         // valor da linha como está impresso
            }
          ]
        }

        Regras:
        - Não inventes. O que não conseguires ler fica null, e uma linha que não consigas ler por inteiro não entra.
        - Usa ponto como separador decimal.
        - Lê apenas as linhas de artigos. Totais, descontos, IVA, portes e observações não são linhas.
        - Se a fatura tiver várias páginas ou colunas, junta todas as linhas de artigos.
        TXT;
    }

    /** @return array<string,mixed>|null */
    private function descodificar(string $texto): ?array
    {
        $texto = trim($texto);
        $texto = preg_replace('/^```(?:json)?|```$/m', '', $texto) ?? $texto;

        $inicio = strpos($texto, '{');
        $fim = strrpos($texto, '}');

        if ($inicio === false || $fim === false || $fim < $inicio) {
            return null;
        }

        $dados = json_decode(substr($texto, $inicio, $fim - $inicio + 1), true);

        return is_array($dados) ? $dados : null;
    }

    /** @return array{type: string, source: array}|null */
    private function blocoDoFicheiro(string $caminho): ?array
    {
        $extensao = strtolower(pathinfo($caminho, PATHINFO_EXTENSION));

        // O PDF vai inteiro: a API lê-o sem precisar do poppler cá.
        if ($extensao === 'pdf') {
            return [
                'type' => 'document',
                'source' => [
                    'type' => 'base64',
                    'media_type' => 'application/pdf',
                    'data' => base64_encode((string) file_get_contents($caminho)),
                ],
            ];
        }

        $tipos = [
            'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png',
            'webp' => 'image/webp', 'gif' => 'image/gif',
        ];

        if (! isset($tipos[$extensao])) {
            return null;
        }

        [$conteudo, $tipo] = $this->imagemReduzida($caminho, $tipos[$extensao]);

        return [
            'type' => 'image',
            'source' => ['type' => 'base64', 'media_type' => $tipo, 'data' => base64_encode($conteudo)],
        ];
    }

    /**
     * Fotos de telemóvel chegam com 4000 px de lado. Acima de LADO_MAXIMO o
     * modelo não lê melhor e a factura de tokens sobe, por isso reduz-se
     * quando o GD existe; sem GD segue como está.
     *
     * @return array{0: string, 1: string}
     */
    private function imagemReduzida(string $caminho, string $tipo): array
    {
        $original = (string) file_get_contents($caminho);

        if (! extension_loaded('gd')) {
            return [$original, $tipo];
        }

        $tamanho = @getimagesize($caminho);

        if ($tamanho === false || max($tamanho[0], $tamanho[1]) <= self::LADO_MAXIMO) {
            return [$original, $tipo];
        }

        $imagem = @imagecreatefromstring($original);

        if ($imagem === false) {
            return [$original, $tipo];
        }

        $escala = self::LADO_MAXIMO / max($tamanho[0], $tamanho[1]);
        $reduzida = imagescale($imagem, (int) round($tamanho[0] * $escala), (int) round($tamanho[1] * $escala));
        imagedestroy($imagem);

        if ($reduzida === false) {
            return [$original, $tipo];
        }

        ob_start();
        imagejpeg($reduzida, null, 90);
        $conteudo = (string) ob_get_clean();
        imagedestroy($reduzida);

        return [$conteudo, 'image/jpeg'];
    }

    /** @param array<string,mixed> $dados */
    private function paraFormatoDoExtractor(array $dados): array
    {
        $linhas = collect($dados['linhas'] ?? [])
            ->filter(fn ($linha) => is_array($linha) && filled($linha['descricao'] ?? null))
            ->map(fn (array $linha) => [
                'description' => (string) $linha['descricao'],
                'quantity' => (float) ($linha['quantidade'] ?? 1),
                'unitPrice' => (float) ($linha['preco_unitario'] ?? 0),
                'vatRate' => (float) ($linha['iva_percentagem'] ?? 0),
                'lineTotal' => (float) ($linha['total_linha'] ?? 0),
                'confidence' => 0.9,
            ])
            ->values()
            ->all();

        return [
            'supplier' => [
                'name' => $dados['fornecedor'] ?? '',
                'taxNumber' => (string) ($dados['nif'] ?? ''),
            ],
            'invoice' => [
                'number' => (string) ($dados['numero_fatura'] ?? ''),
                'date' => $this->dataPtPt($dados['data'] ?? null),
                'total' => (float) ($dados['total'] ?? 0),
                'vatTotal' => (float) ($dados['total_iva'] ?? 0),
                'currency' => 'EUR',
            ],
            'products' => $linhas,
        ];
    }

    /** O resto do sistema fala em dd/mm/aaaa; o modelo devolve ISO. */
    private function dataPtPt(?string $data): string
    {
        if ($data === null || ! preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', trim($data), $partes)) {
            return '';
        }

        return "{$partes[3]}/{$partes[2]}/{$partes[1]}";
    }
}
