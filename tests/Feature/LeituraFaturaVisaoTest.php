<?php

namespace Tests\Feature;

use App\Services\PaperInvoice\LeituraFatura;
use App\Services\PaperInvoice\PaperInvoiceExtractor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Numa foto de papel amarrotado o OCR devolve ruido - linhas como
 * "Fovisey [ERNE pew BL aw Te am iN ex] aaa". Quando isso acontece, a leitura
 * passa ao modelo de visao; caso contrario nao se gasta a chamada.
 */
class LeituraFaturaVisaoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'paper_invoice.claude.api_key' => 'chave-de-teste',
            'paper_invoice.claude.model' => 'claude-sonnet-5',
        ]);
    }

    public function test_ocr_sem_linhas_passa_ao_modelo_de_visao(): void
    {
        $this->fingirOcr(['products' => [], 'invoice' => ['number' => '', 'date' => '', 'total' => 0.0, 'vatTotal' => 0.0]]);
        Http::fake([$this->endpoint() => Http::response($this->respostaDoModelo())]);

        $dados = app(LeituraFatura::class)->ler($this->ficheiro());

        $this->assertSame('visao', $dados['fonte']);
        $this->assertCount(2, $dados['products']);
        $this->assertSame('ERUNE primetanil - 5 LT', $dados['products'][0]['description']);
        $this->assertSame(103.5, $dados['products'][0]['unitPrice']);
        $this->assertSame('Casa Queridos', $dados['supplier']['name']);
        $this->assertSame('20/07/2026', $dados['invoice']['date']);
    }

    /** Com QR lido, o numero, a data e o total do documento ganham ao modelo. */
    public function test_o_qr_continua_a_mandar_no_cabecalho(): void
    {
        $this->fingirOcr([
            'products' => [],
            'qrData' => 'A:505415852*F:20260720*G:FT 1100/135909*O:394.21',
            'invoice' => ['number' => 'FT 1100/135909', 'date' => '20/07/2026', 'total' => 394.21, 'vatTotal' => 22.31],
        ]);
        Http::fake([$this->endpoint() => Http::response($this->respostaDoModelo(['numero_fatura' => '135909', 'total' => 999.0]))]);

        $dados = app(LeituraFatura::class)->ler($this->ficheiro());

        $this->assertSame('FT 1100/135909', $dados['invoice']['number']);
        $this->assertSame(394.21, $dados['invoice']['total']);
        $this->assertCount(2, $dados['products']);
    }

    /** Linhas que somam o total sao boas: nao se gasta a chamada. */
    public function test_ocr_que_bate_certo_nao_chama_o_modelo(): void
    {
        Http::fake();
        $this->fingirOcr([
            'invoice' => ['number' => 'FT 1/1', 'date' => '20/07/2026', 'total' => 100.0, 'vatTotal' => 0.0],
            'products' => [['description' => 'Adubo', 'quantity' => 1, 'unitPrice' => 100.0, 'vatRate' => 0, 'lineTotal' => 100.0, 'confidence' => 0.8]],
        ]);

        $dados = app(LeituraFatura::class)->ler($this->ficheiro());

        $this->assertSame('ocr', $dados['fonte']);
        Http::assertNothingSent();
    }

    public function test_sem_chave_fica_se_pelo_ocr_e_diz_o(): void
    {
        config(['paper_invoice.claude.api_key' => null]);
        Http::fake();
        $this->fingirOcr(['products' => []]);

        $dados = app(LeituraFatura::class)->ler($this->ficheiro());

        $this->assertSame('ocr', $dados['fonte']);
        $this->assertContains(
            'O OCR nao conseguiu ler as linhas. Configure ANTHROPIC_API_KEY para a leitura assistida.',
            $dados['warnings']
        );
        Http::assertNothingSent();
    }

    /** Se a API falhar, a despesa entra à mão — não se perde o que já se sabia. */
    public function test_falha_da_api_nao_rebenta_a_leitura(): void
    {
        $this->fingirOcr(['products' => [], 'invoice' => ['number' => 'FT 1/1', 'date' => '', 'total' => 50.0, 'vatTotal' => 0.0]]);
        Http::fake([$this->endpoint() => Http::response('erro', 500)]);

        $dados = app(LeituraFatura::class)->ler($this->ficheiro());

        $this->assertSame('ocr', $dados['fonte']);
        $this->assertSame('FT 1/1', $dados['invoice']['number']);
        $this->assertContains('A leitura assistida falhou; ficam apenas os dados do OCR.', $dados['warnings']);
    }

    private function endpoint(): string
    {
        return 'api.anthropic.com/*';
    }

    private function ficheiro(): string
    {
        $caminho = storage_path('app/fatura-de-teste.jpg');

        if (! is_dir(dirname($caminho))) {
            mkdir(dirname($caminho), 0775, true);
        }

        file_put_contents($caminho, base64_decode(
            '/9j/4AAQSkZJRgABAQEAYABgAAD/2wBDAAgGBgcGBQgHBwcJCQgKDBQNDAsLDBkSEw8UHRofHh0a'
            .'HBwgJC4nICIsIxwcKDcpLDAxNDQ0Hyc5PTgyPC4zNDL/wAALCAABAAEBAREA/8QAFAABAAAAAAAA'
            .'AAAAAAAAAAAACf/EABQQAQAAAAAAAAAAAAAAAAAAAAD/2gAIAQEAAD8AKp//2Q=='
        ));

        return $caminho;
    }

    private function fingirOcr(array $dados): void
    {
        $base = [
            'source' => 'paper_invoice_photo',
            'supplier' => ['name' => 'Produtos para a agricultura, Lda.', 'taxNumber' => '505415852'],
            'invoice' => ['number' => '', 'date' => '', 'total' => 0.0, 'vatTotal' => 0.0],
            'products' => [],
            'confidence' => 0.3,
            'needsManualReview' => true,
            'rawText' => 'Fovisey [ERNE pew BL aw Te am iN ex] aaa',
            'qrData' => null,
            'warnings' => ['Nao foram encontradas linhas de produtos.'],
        ];

        $this->instance(PaperInvoiceExtractor::class, new class(array_replace($base, $dados)) extends PaperInvoiceExtractor
        {
            public function __construct(private readonly array $resultado)
            {
            }

            public function extract(string $documentPath): array
            {
                return $this->resultado;
            }
        });
    }

    private function respostaDoModelo(array $substituir = []): array
    {
        $json = array_replace([
            'fornecedor' => 'Casa Queridos',
            'nif' => '505415852',
            'numero_fatura' => 'FT 1100/135909',
            'data' => '2026-07-20',
            'total' => 394.21,
            'total_iva' => 22.31,
            'linhas' => [
                ['descricao' => 'ERUNE primetanil - 5 LT', 'quantidade' => 2, 'preco_unitario' => 103.5, 'iva_percentagem' => 6, 'total_linha' => 207.0],
                ['descricao' => 'BANJO fluziname - 5 LT', 'quantidade' => 1, 'preco_unitario' => 164.9, 'iva_percentagem' => 6, 'total_linha' => 164.9],
            ],
        ], $substituir);

        return [
            'content' => [['type' => 'text', 'text' => "```json\n".json_encode($json)."\n```"]],
        ];
    }
}
