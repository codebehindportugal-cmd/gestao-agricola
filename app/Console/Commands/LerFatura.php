<?php

namespace App\Console\Commands;

use App\Services\PaperInvoice\LeitorFaturaClaude;
use App\Services\PaperInvoice\LeituraFatura;
use App\Services\PaperInvoice\PalavrasPosicionadas;
use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

/**
 * Le faturas pela linha de comandos e mostra o que saiu.
 *
 * Serve duas coisas: perceber uma fatura que correu mal, e passar uma pasta
 * inteira de fotografias para ver quantas se lem sem ajuda. E' esse numero -
 * quantas em dez - que decide se e' preciso mais alguma coisa alem do OCR.
 */
class LerFatura extends Command
{
    /** Extensoes que vale a pena tentar ler numa pasta. */
    private const EXTENSOES = ['jpg', 'jpeg', 'png', 'webp', 'bmp', 'tiff', 'pdf'];

    protected $signature = 'agri:ler-fatura
        {caminho : Ficheiro ou pasta com faturas (fotos ou PDF)}
        {--tsv= : Pasta onde guardar o OCR das que falharem, para analise}
        {--limite=50 : Maximo de ficheiros a ler quando o caminho e uma pasta}';

    protected $description = 'Lê faturas e mostra o cabeçalho, as linhas e os avisos.';

    public function handle(LeituraFatura $leitura, LeitorFaturaClaude $visao): int
    {
        $caminho = $this->argument('caminho');

        if (! file_exists($caminho)) {
            $this->error("Não encontrei: {$caminho}");

            return self::FAILURE;
        }

        $this->ambiente($visao);

        return is_dir($caminho)
            ? $this->lerPasta($leitura, $caminho)
            : $this->lerUma($leitura, $caminho);
    }

    private function ambiente(LeitorFaturaClaude $visao): void
    {
        $this->line('Leitura assistida por IA: '.(config('paper_invoice.claude.activa') ? 'ligada' : '<fg=yellow>desligada</>')
            .' · chave '.($visao->disponivel() ? 'utilizável' : 'não utilizada'));
        $this->line('Idiomas do tesseract: '.$this->idiomas());
        $this->line('ImageMagick: '.($this->existe('convert') || $this->existe('magick') ? 'sim' : '<fg=red>em falta</>')
            .' · zbarimg: '.($this->existe('zbarimg') ? 'sim' : '<fg=red>em falta</>'));
        $this->newLine();
    }

    private function lerUma(LeituraFatura $leitura, string $caminho): int
    {
        $tamanho = @getimagesize($caminho);

        if ($tamanho !== false) {
            $this->line("Imagem: {$tamanho[0]}x{$tamanho[1]} px");

            if (max($tamanho[0], $tamanho[1]) < 1600) {
                $this->warn('  Imagem pequena de mais para ler a tabela. Fotografe na resolução máxima.');
            }
        }

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
                ['Descrição', 'Qtd', 'Preço', 'Desc %', 'IVA %', 'Total', 'Confiança'],
                collect($dados['products'])->map(fn (array $linha) => [
                    $linha['description'],
                    $linha['quantity'],
                    $linha['unitPrice'],
                    $linha['discountRate'] ?? 0,
                    $linha['vatRate'],
                    $linha['lineTotal'],
                    $linha['confidence'],
                ])->all()
            );
        }

        foreach ($dados['warnings'] ?? [] as $aviso) {
            $this->warn($aviso);
        }

        $this->guardarOcr($caminho, $dados);

        if ($this->output->isVerbose()) {
            $this->newLine();
            $this->line('--- texto do OCR ---');
            $this->line($dados['rawText'] ?? '');
        }

        return self::SUCCESS;
    }

    private function lerPasta(LeituraFatura $leitura, string $pasta): int
    {
        $ficheiros = collect(scandir($pasta) ?: [])
            ->reject(fn (string $nome) => in_array($nome, ['.', '..'], true))
            ->map(fn (string $nome) => rtrim($pasta, '/\\').DIRECTORY_SEPARATOR.$nome)
            ->filter(fn (string $caminho) => is_file($caminho)
                && in_array(strtolower(pathinfo($caminho, PATHINFO_EXTENSION)), self::EXTENSOES, true))
            ->take((int) $this->option('limite'))
            ->values();

        if ($ficheiros->isEmpty()) {
            $this->warn('Nenhuma fatura nesta pasta.');

            return self::SUCCESS;
        }

        $this->info("A ler {$ficheiros->count()} fatura(s). Demora alguns segundos cada.");
        $this->newLine();

        $linhasTabela = [];
        $lidas = 0;

        foreach ($ficheiros as $ficheiro) {
            $dados = $leitura->ler($ficheiro);
            $artigos = $dados['products'] ?? [];
            $certas = collect($artigos)->where('confidence', '>=', 0.95)->count();
            // "Lida" e' ter linhas em que as contas fecham. Os avisos de
            // ferramentas em falta sao do servidor, nao da leitura, e vao numa
            // coluna a parte para nao mascararem o numero que interessa.
            $completa = $artigos !== [] && $certas === count($artigos);

            $lidas += $completa ? 1 : 0;

            $linhasTabela[] = [
                basename($ficheiro),
                $dados['invoice']['number'] ?: '—',
                filled($dados['qrData'] ?? null) ? 'sim' : 'não',
                count($artigos),
                $certas,
                count($dados['warnings'] ?? []) ?: '',
                $completa ? '<fg=green>ok</>' : '<fg=yellow>rever</>',
            ];

            $this->guardarOcr($ficheiro, $dados, ! $completa);
        }

        $this->table(['Ficheiro', 'Documento', 'QR', 'Linhas', 'Certas', 'Avisos', ''], $linhasTabela);
        $this->info("Lidas sem ajuda: {$lidas} de {$ficheiros->count()}.");

        if ($this->option('tsv')) {
            $this->line('O OCR das que ficaram por rever está em: '.$this->option('tsv'));
        }

        return self::SUCCESS;
    }

    /**
     * Guarda o texto do OCR das faturas que falharam.
     *
     * E' com isto que se afina o leitor sem andar a passar fotografias para
     * trás e para a frente: o TSV tem as palavras e as coordenadas, que e'
     * tudo o que o parser vê.
     */
    private function guardarOcr(string $ficheiro, array $dados, bool $apenasSeFalhou = false): void
    {
        $pasta = $this->option('tsv');

        if (! $pasta || ($apenasSeFalhou && ($dados['products'] ?? []) !== [])) {
            return;
        }

        if (! is_dir($pasta)) {
            mkdir($pasta, 0775, true);
        }

        $nome = pathinfo($ficheiro, PATHINFO_FILENAME);
        file_put_contents($pasta.DIRECTORY_SEPARATOR.$nome.'.txt', $dados['rawText'] ?? '');
    }

    private function existe(string $programa): bool
    {
        try {
            $processo = new Process(['which', $programa]);
            $processo->setTimeout(5)->run();

            return $processo->isSuccessful();
        } catch (\Throwable) {
            return false;
        }
    }

    /** Sem o `por` instalado, acentos e cabecalhos lem-se pior. */
    private function idiomas(): string
    {
        try {
            $processo = new Process(['tesseract', '--list-langs']);
            $processo->setTimeout(10)->run();

            $linhas = array_slice(preg_split('/\R/', trim($processo->getOutput())) ?: [], 1);

            return $linhas === [] ? 'não foi possível listar' : implode(', ', $linhas);
        } catch (\Throwable) {
            return 'não foi possível listar';
        }
    }
}
