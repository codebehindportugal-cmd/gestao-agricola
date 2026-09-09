<?php

namespace Tests\Feature;

use App\Services\PaperInvoice\PalavrasPosicionadas;
use App\Services\PaperInvoice\PaperInvoiceExtractor;
use PHPUnit\Framework\TestCase;

/**
 * A fatura real que nao se conseguia ler.
 *
 * O ficheiro de apoio e' o TSV que o tesseract devolveu para a fotografia da
 * fatura 132690 da Casa Queridos - folha vincada, marca de agua por cima da
 * tabela e as celulas da quantidade e do IVA perdidas. Guardado tal e qual
 * para que qualquer mexida no leitor tenha de continuar a dar isto:
 *
 *   BANJO fluziname - 5 LT (AV 1199)   2 x 164,90 = 329,80, IVA 6%
 *
 * A quantidade sai do total a dividir pelo preco, o ponto decimal do total
 * (que o OCR perdeu, devolvendo "329" e "800") e' reposto pela distancia
 * entre as duas palavras, e a taxa de IVA vem do rodape.
 */
class FaturaCasaQueridosTest extends TestCase
{
    private const QR = 'A:505415852*B:241752566*C:PT*D:FT*E:N*F:20260410'
        .'*G:FT 1100/132690*H:JF22DCC3*N:19.79*O:349.59';

    public function test_le_a_fatura_da_casa_queridos(): void
    {
        $tsv = file_get_contents(__DIR__.'/../Fixtures/casa-queridos-132690.tsv');
        $texto = PalavrasPosicionadas::textoDeTsv($tsv);

        $dados = (new PaperInvoiceExtractor)->parseText($texto, self::QR, [], $tsv);

        $this->assertSame('FT 1100/132690', $dados['invoice']['number']);
        $this->assertSame('10/04/2026', $dados['invoice']['date']);
        $this->assertSame(349.59, $dados['invoice']['total']);

        $this->assertCount(1, $dados['products']);

        $linha = $dados['products'][0];
        $this->assertSame('BANJO fluziname - 5 LT (AV 1199)', $linha['description']);
        $this->assertSame(2.0, $linha['quantity']);
        $this->assertSame(164.9, $linha['unitPrice']);
        $this->assertSame(329.8, $linha['lineTotal']);
        $this->assertSame(6.0, $linha['vatRate']);
        $this->assertSame(0.0, $linha['discountRate']);
        // As contas da linha fecham com o que esta impresso.
        $this->assertSame(0.95, $linha['confidence']);
        $this->assertSame([], $dados['warnings']);
    }

    /** O numero do lote nao pode entrar como preco nem como quantidade. */
    public function test_o_lote_nao_se_confunde_com_os_numeros_da_linha(): void
    {
        $tsv = file_get_contents(__DIR__.'/../Fixtures/casa-queridos-132690.tsv');

        $linha = (new PaperInvoiceExtractor)
            ->parseText(PalavrasPosicionadas::textoDeTsv($tsv), self::QR, [], $tsv)['products'][0];

        $this->assertNotSame(2510010010.0, $linha['unitPrice']);
        $this->assertLessThan(1000, $linha['quantity']);
    }
}
