<?php

namespace App\Console\Commands;

use App\Services\PaperInvoice\LeitorFaturaClaude;
use App\Services\PaperInvoice\LeituraFatura;
use Illuminate\Console\Command;

/**
 * Le uma fatura pela linha de comandos e mostra o que saiu.
 *
 * Quando o ecra devolve uma fatura sem linhas, a pergunta e sempre a mesma:
 * foi o OCR, foi a chave, foi o modelo? Isto responde sem ter de repetir o
 * carregamento no browser.
 */
class LerFatura extends Command
{
    protected $signature = 'agri:ler-fatura {caminho : Ficheiro da fatura (foto ou PDF)}';

    protected $description = 'Lê uma fatura e mostra o cabeçalho, as linhas e os avisos.';

    /** Sem o `por` instalado, acentos e cabecalhos lem-se pior. */
    private function idiomas(): string
    {
        try {
            $processo = new \Symfony\Component\Process\Process(['tesseract', '--list-langs']);
            $processo->setTimeout(10)->run();

            $linhas = array_slice(preg_split('/\R/', trim($processo->getOutput())) ?: [], 1);

            return $linhas === [] ? 'não foi possível listar' : implode(', ', $linhas);
        } catch (\Throwable) {
            return 'não foi possível listar';
        }
    }

    public function handle(LeituraFatura $leitura, LeitorFaturaClaude $visao): int
    {
        $caminho = $this->argument('caminho');

        if (! is_file($caminho)) {
            $this->error("Não encontrei o ficheiro: {$caminho}");

            return self::FAILURE;
        }

        $tamanho = @getimagesize($caminho);

        if ($tamanho !== false) {
            $lado = max($tamanho[0], $tamanho[1]);
            $this->line("Imagem: {$tamanho[0]}x{$tamanho[1]} px");

            // Abaixo disto os algarismos da tabela ficam com dois ou tres
            // pixeis de altura e nao ha OCR que os leia.
            if ($lado < 1600) {
                $this->warn('  Imagem pequena de mais para ler a tabela. Fotografe na resolução máxima.');
            }
        }

        $this->line('Leitura assistida por IA: '.(config('paper_invoice.claude.activa') ? 'ligada' : '<fg=yellow>desligada</>')
            .' · chave '.($visao->disponivel() ? 'utilizável' : 'não utilizada'));
        $this->line('Modelo: '.config('paper_invoice.claude.model'));

        $this->line('Idiomas do tesseract: '.$this->idiomas());

        foreach (['tesseract', 'zbarimg', 'pdftotext', 'pdftoppm'] as $programa) {
            $configurado = config('paper_invoice.binaries.'.$programa);
            $this->line("  {$programa}: ".($configurado ?: 'procurado no PATH'));
        }

        if (! function_exists('proc_open')) {
            $this->warn('proc_open está desactivado neste PHP: nenhum programa externo corre, logo não há OCR.');
        }

        $this->newLine();

        $dados = $leitura->ler($caminho);

        $this->info('Fonte: '.($dados['fonte'] ?? 'ocr'));
        $this->line('Fornecedor: '.($dados['supplier']['name'] ?: '—').'  NIF: '.($dados['supplier']['taxNumber'] ?: '—'));
        $this->line('Documento: '.($dados['invoice']['number'] ?: '—')
            .'  Data: '.($dados['invoice']['date'] ?: '—')
            .'  Total: '.($dados['invoice']['total'] ?: '—'));
        $this->line('QR: '.(filled($dados['qrData'] ?? null) ? 'lido' : 'não lido'));
        $this->newLine();

        if ($dados['products'] === []) {
            $this->warn('Sem linhas de produtos.');
        } else {
            $this->table(
                ['Descrição', 'Qtd', 'Preço', 'Desc %', 'IVA %', 'Total'],
                collect($dados['products'])->map(fn (array $linha) => [
                    $linha['description'],
                    $linha['quantity'],
                    $linha['unitPrice'],
                    $linha['discountRate'] ?? 0,
                    $linha['vatRate'],
                    $linha['lineTotal'],
                ])->all()
            );
        }

        foreach ($dados['warnings'] ?? [] as $aviso) {
            $this->warn($aviso);
        }

        if ($this->output->isVerbose()) {
            $this->newLine();
            $this->line('--- texto do OCR ---');
            $this->line($dados['rawText'] ?? '');
        }

        return self::SUCCESS;
    }
}
