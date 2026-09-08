<?php

namespace App\Http\Controllers;

use App\Models\Despesa;
use App\Models\Produto;
use App\Services\PaperInvoice\LeituraFatura;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Le uma foto ou PDF de uma fatura e devolve o cabecalho e as linhas.
 *
 * O QR da AT - o unico que o browser sabe ler sozinho - traz por lei apenas o
 * cabecalho: NIF, data, numero, IVA e total. As linhas dos produtos nao estao
 * la, e e por isso que a fatura entrava sempre sem artigos. Aqui corre-se o
 * mesmo extractor do gestao.ateneya.com: OCR do papel, leitura do QR e
 * reconhecimento das linhas.
 *
 * A resposta nunca grava nada: e o utilizador que confirma no formulario.
 */
class FaturaExtracaoController extends Controller
{
    public function __invoke(Request $request, LeituraFatura $leitura): JsonResponse
    {
        $this->authorize('create', Despesa::class);

        $request->validate([
            'ficheiro' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png,webp,bmp,tiff', 'max:20480'],
        ]);

        $path = $request->file('ficheiro')->store('faturas-leitura/tmp');

        try {
            $dados = $leitura->ler(Storage::path($path));
        } finally {
            Storage::delete($path);
        }

        return response()->json($this->formatar($dados));
    }

    /** Devolve so o que o formulario precisa, ja com os produtos do catalogo sugeridos. */
    private function formatar(array $dados): array
    {
        $produtos = Produto::query()->get(['id', 'nome', 'codigo_interno']);

        $linhas = collect($dados['products'] ?? [])
            ->map(fn (array $linha) => [
                'descricao' => $linha['description'] ?? '',
                'quantidade' => round((float) ($linha['quantity'] ?? 1), 3),
                'preco_unitario' => round((float) ($linha['unitPrice'] ?? 0), 4),
                'iva_percentagem' => $this->taxaIvaAceite((float) ($linha['vatRate'] ?? 0)),
                'total_linha' => round((float) ($linha['lineTotal'] ?? 0), 2),
                'confianca' => round((float) ($linha['confidence'] ?? 0), 2),
                'produto_id' => $this->produtoSugerido($linha['description'] ?? '', $produtos),
            ])
            ->values()
            ->all();

        return [
            'fornecedor' => $dados['supplier']['name'] ?? null,
            'nif' => $dados['supplier']['taxNumber'] ?: null,
            'numero_fatura' => $dados['invoice']['number'] ?: null,
            'data' => $this->dataIso($dados['invoice']['date'] ?? ''),
            'total' => (float) ($dados['invoice']['total'] ?? 0) ?: null,
            'total_iva' => (float) ($dados['invoice']['vatTotal'] ?? 0) ?: null,
            'linhas' => $linhas,
            'fonte' => $dados['fonte'] ?? 'ocr',
            'confianca' => $dados['confidence'] ?? 0,
            'rever' => $dados['needsManualReview'] ?? true,
            'avisos' => $dados['warnings'] ?? [],
        ];
    }

    /** O extractor devolve dd/mm/aaaa; o campo de data do formulario quer aaaa-mm-dd. */
    private function dataIso(string $data): ?string
    {
        if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', trim($data), $partes)) {
            return "{$partes[3]}-{$partes[2]}-{$partes[1]}";
        }

        return preg_match('/^\d{4}-\d{2}-\d{2}$/', trim($data)) ? trim($data) : null;
    }

    /**
     * O formulario so aceita as taxas legais. Uma taxa lida a torto (um "2" que
     * era "23") entraria em silencio e falseava o IVA da despesa.
     */
    private function taxaIvaAceite(float $taxa): float
    {
        $aceites = DespesaManagementController::TAXAS_IVA;

        foreach ($aceites as $valor) {
            if (abs($taxa - $valor) < 0.5) {
                return (float) $valor;
            }
        }

        return 0.0;
    }

    /** Produto do catalogo cujo nome ou codigo aparece na descricao lida. */
    private function produtoSugerido(string $descricao, $produtos): ?int
    {
        $descricao = Str::lower(trim($descricao));

        if ($descricao === '') {
            return null;
        }

        foreach ($produtos as $produto) {
            $nome = Str::lower((string) $produto->nome);

            if ($nome !== '' && mb_strlen($nome) >= 4 && str_contains($descricao, $nome)) {
                return $produto->id;
            }

            $codigo = Str::lower((string) $produto->codigo_interno);

            if ($codigo !== '' && mb_strlen($codigo) >= 3 && str_contains($descricao, $codigo)) {
                return $produto->id;
            }
        }

        return null;
    }
}
