<?php

namespace Tests\Unit;

use App\Services\PaperInvoice\PaperInvoiceExtractor;
use PHPUnit\Framework\TestCase;

/**
 * O QR da AT traz so o cabecalho: as linhas dos produtos tem de sair do texto.
 * Estes testes fixam o reconhecimento das linhas sem depender do OCR instalado.
 */
class PaperInvoiceExtractorTest extends TestCase
{
    public function test_le_as_linhas_de_uma_fatura_com_iva_por_linha(): void
    {
        $texto = <<<'TXT'
        AGROEXCLUSIVE LDA
        NIF: 507123456
        Fatura FT 1000/1755          Data: 08-09-2026

        Referencia Designacao            Qtd   Preco   IVA    Total
        AD-20 Adubo foliar 20L           2     120,00  6      254,40
        NIT-5 Nitrato de calcio 25kg     4     48,50   6      205,64
        Total documento                                       577,00
        TXT;

        $dados = (new PaperInvoiceExtractor)->parseText($texto);

        $this->assertCount(2, $dados['products']);
        $this->assertSame('AD-20 Adubo foliar 20L', $dados['products'][0]['description']);
        $this->assertSame(2.0, $dados['products'][0]['quantity']);
        $this->assertSame(120.0, $dados['products'][0]['unitPrice']);
        $this->assertSame(6.0, $dados['products'][0]['vatRate']);
        $this->assertSame(254.40, $dados['products'][0]['lineTotal']);
        $this->assertSame(577.0, $dados['invoice']['total']);
    }

    public function test_le_as_linhas_sem_coluna_de_iva(): void
    {
        $texto = <<<'TXT'
        Casa Queridos
        Designacao                 Qtd    Preco    Total
        Montana 5L                 4      45,00    180,00
        Total a pagar                              180,00
        TXT;

        $dados = (new PaperInvoiceExtractor)->parseText($texto);

        $this->assertCount(1, $dados['products']);
        $this->assertSame('Montana 5L', $dados['products'][0]['description']);
        $this->assertSame(4.0, $dados['products'][0]['quantity']);
        $this->assertSame(45.0, $dados['products'][0]['unitPrice']);
    }

    /** O QR e a fonte exacta do numero, da data e do total; o texto so preenche as faltas. */
    public function test_o_qr_manda_no_cabecalho(): void
    {
        $qr = 'A:507123456*B:999999990*C:PT*D:FT*E:N*F:20260908*G:FT 1000/1755*H:ABCD1234*N:31.85*O:577.00*Q:xX*R:1234';

        $dados = (new PaperInvoiceExtractor)->parseText("Linha qualquer sem estrutura\n", $qr);

        $this->assertSame('507123456', $dados['supplier']['taxNumber']);
        $this->assertSame('FT 1000/1755', $dados['invoice']['number']);
        $this->assertSame('08/09/2026', $dados['invoice']['date']);
        $this->assertSame(577.0, $dados['invoice']['total']);
    }

    public function test_avisa_quando_a_soma_das_linhas_nao_bate_com_o_total(): void
    {
        $texto = <<<'TXT'
        Designacao                 Qtd    Preco    IVA   Total
        Adubo foliar 20L           2      120,00   6     254,40
        Total documento                                  900,00
        TXT;

        $dados = (new PaperInvoiceExtractor)->parseText($texto);

        $this->assertTrue($dados['needsManualReview']);
        $this->assertContains('A soma das linhas nao coincide com o total da fatura.', $dados['warnings']);
    }
}
