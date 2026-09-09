<?php

namespace Tests\Unit;

use App\Services\PaperInvoice\PalavrasPosicionadas;
use App\Services\PaperInvoice\TabelaFatura;
use PHPUnit\Framework\TestCase;

/**
 * A tabela lida pelas posicoes das colunas, e nao pela ordem das palavras.
 * O caso que interessa e' o da Casa Queridos: colunas D1% e D2% vazias, que
 * numa leitura por texto corrido faziam o IVA escorregar para o desconto.
 */
class TabelaFaturaTest extends TestCase
{
    private function layoutCasaQueridos(): string
    {
        // Tal como o `pdftotext -layout` devolve: colunas alinhadas por espacos.
        return <<<'TXT'
        CÓDIGO      DESCRIÇÃO                          LOTE          QT  V.UNITÁRIO  D1% D2%   IVA     TOTAL
        10190133    BANJO fluziname - 5 LT (AV 1199)   2510010010  2.00    164.9000              6%   329.800
        10110321    ERUNE primetanil - 5 LT (AV 1761)  6_102624    2.00    103.5000              6%   207.000

        Total ilíquido s/iva                                                                          536.80
        TXT;
    }

    public function test_le_as_colunas_pela_posicao_e_nao_pela_ordem(): void
    {
        $linhas = (new TabelaFatura)->linhas(
            PalavrasPosicionadas::deTextoAlinhado($this->layoutCasaQueridos())
        );

        $this->assertCount(2, $linhas);

        $this->assertStringContainsString('BANJO fluziname', $linhas[0]['description']);
        $this->assertSame(2.0, $linhas[0]['quantity']);
        $this->assertSame(164.9, $linhas[0]['unitPrice']);
        // As colunas D1% e D2% estao vazias: o 6% e' IVA, nao desconto.
        $this->assertSame(0.0, $linhas[0]['discountRate']);
        $this->assertSame(6.0, $linhas[0]['vatRate']);
        $this->assertSame(329.8, $linhas[0]['lineTotal']);
        // 2 x 164,90 = 329,80: as contas fecham.
        $this->assertSame(0.95, $linhas[0]['confidence']);
    }

    public function test_para_na_linha_dos_totais(): void
    {
        $linhas = (new TabelaFatura)->linhas(
            PalavrasPosicionadas::deTextoAlinhado($this->layoutCasaQueridos())
        );

        $this->assertNotContains('Total', array_column($linhas, 'description'));
    }

    public function test_dois_descontos_em_cascata_dao_uma_percentagem_efectiva(): void
    {
        $texto = <<<'TXT'
        DESCRIÇÃO                 QT   V.UNITÁRIO   D1%   D2%   IVA    TOTAL
        Adubo foliar 20L        2.00     100.0000    10     5    6%   171.000
        TXT;

        $linhas = (new TabelaFatura)->linhas(PalavrasPosicionadas::deTextoAlinhado($texto));

        // 10% e depois 5% valem 14,5%, e nao 15%.
        $this->assertSame(14.5, $linhas[0]['discountRate']);
        $this->assertSame(6.0, $linhas[0]['vatRate']);
        $this->assertSame(171.0, $linhas[0]['lineTotal']);
        $this->assertSame(0.95, $linhas[0]['confidence']);
    }

    /** Linhas em que as contas nao fecham entram com confianca baixa, para nao passarem caladas. */
    public function test_linha_que_nao_fecha_fica_com_confianca_baixa(): void
    {
        $texto = <<<'TXT'
        DESCRIÇÃO             QT   V.UNITÁRIO   IVA    TOTAL
        Adubo foliar 20L    2.00     100.0000    6%   900.000
        TXT;

        $linhas = (new TabelaFatura)->linhas(PalavrasPosicionadas::deTextoAlinhado($texto));

        $this->assertSame(0.4, $linhas[0]['confidence']);
    }

    public function test_sem_cabecalho_de_tabela_nao_inventa_linhas(): void
    {
        $texto = "Fatura FT 1100/132690\nObrigado pela preferencia\n";

        $this->assertSame([], (new TabelaFatura)->linhas(PalavrasPosicionadas::deTextoAlinhado($texto)));
    }

    /** O TSV do tesseract entra pelo mesmo caminho que o PDF. */
    public function test_le_tambem_o_tsv_do_tesseract(): void
    {
        $tsv = "level\tpage\tblock\tpar\tline\tword\tleft\ttop\twidth\theight\tconf\ttext\n"
            ."5\t1\t1\t1\t1\t1\t100\t500\t120\t20\t92\tDESCRIÇÃO\n"
            ."5\t1\t1\t1\t1\t2\t600\t500\t40\t20\t92\tQT\n"
            ."5\t1\t1\t1\t1\t3\t700\t500\t120\t20\t92\tV.UNITÁRIO\n"
            ."5\t1\t1\t1\t1\t4\t900\t500\t40\t20\t92\tIVA\n"
            ."5\t1\t1\t1\t1\t5\t1000\t500\t80\t20\t92\tTOTAL\n"
            ."5\t1\t1\t1\t2\t1\t100\t540\t200\t20\t88\tAdubo\n"
            ."5\t1\t1\t1\t2\t2\t180\t541\t60\t20\t88\tfoliar\n"
            ."5\t1\t1\t1\t2\t3\t600\t540\t40\t20\t80\t2.00\n"
            ."5\t1\t1\t1\t2\t4\t700\t540\t100\t20\t80\t100.0000\n"
            ."5\t1\t1\t1\t2\t5\t900\t540\t40\t20\t80\t6%\n"
            ."5\t1\t1\t1\t2\t6\t1000\t540\t80\t20\t80\t200.000\n";

        $linhas = (new TabelaFatura)->linhas(PalavrasPosicionadas::deTsv($tsv));

        $this->assertCount(1, $linhas);
        $this->assertSame('Adubo foliar', $linhas[0]['description']);
        $this->assertSame(100.0, $linhas[0]['unitPrice']);
        $this->assertSame(200.0, $linhas[0]['lineTotal']);
    }
}
