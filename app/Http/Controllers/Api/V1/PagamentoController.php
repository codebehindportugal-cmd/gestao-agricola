<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\RespondeJson;
use App\Http\Controllers\Controller;
use App\Models\Fornecedor;
use App\Models\PagamentoFornecedor;
use App\Services\CompressorImagens;
use App\Services\ContaCorrenteFornecedores;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * Recibos dos fornecedores e o que se lhes deve.
 *
 * O recibo chega em papel, como as faturas: o Andre fotografa-o, o chat le
 * o fornecedor, o valor e as faturas que ele diz pagar, e regista aqui.
 */
class PagamentoController extends Controller
{
    use RespondeJson;

    public function __construct(private readonly ContaCorrenteFornecedores $conta)
    {
    }

    /** GET /fornecedores/saldos — quanto se deve a cada fornecedor. */
    public function saldos(Request $request): JsonResponse
    {
        $saldos = $this->conta->saldos($request->query('fornecedor'));

        return $this->ok([
            'total_em_divida' => round((float) $saldos->sum(fn ($s) => max(0, $s['saldo'])), 2),
            'fornecedores' => $saldos->all(),
        ]);
    }

    /** GET /fornecedores/{referencia}/conta — faturas em aberto, recibos e extrato. */
    public function conta(string $referencia): JsonResponse
    {
        $fornecedor = $this->resolverFornecedor($referencia);

        if ($fornecedor instanceof JsonResponse) {
            return $fornecedor;
        }

        $saldo = $this->conta->saldos()->firstWhere('id', $fornecedor->id);

        return $this->ok([
            'fornecedor' => ['id' => $fornecedor->id, 'nome' => $fornecedor->nome, 'nif' => $fornecedor->nif],
            'saldo' => $saldo['saldo'] ?? 0.0,
            'por_imputar' => $saldo['por_imputar'] ?? 0.0,
            'faturas_em_aberto' => $this->conta->faturas($fornecedor, soEmAberto: true)->all(),
            'pagamentos' => $fornecedor->pagamentos()->with('despesas:id,numero_fatura')->latest('data')->limit(20)->get()
                ->map(fn (PagamentoFornecedor $p) => $this->formatarPagamento($p))->all(),
            'extrato' => $this->conta->extrato($fornecedor),
        ]);
    }

    /** POST /pagamentos — regista um recibo. */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'fornecedor' => ['required'],
            'data' => ['required', 'date'],
            'valor' => ['required', 'numeric', 'gt:0'],
            'numero_recibo' => ['nullable', 'string', 'max:100'],
            'metodo' => ['nullable', 'string', Rule::in(PagamentoFornecedor::METODOS)],
            'notas' => ['nullable', 'string'],
            'referencia_externa' => ['nullable', 'string', 'max:255'],
            'imputar_automaticamente' => ['nullable', 'boolean'],
            'faturas' => ['nullable', 'array'],
            'faturas.*' => ['required'],
        ]);

        if ($validator->fails()) {
            return $this->erro422($validator->errors()->toArray());
        }

        $data = $validator->validated();
        $fornecedor = $this->resolverFornecedor($data['fornecedor']);

        if ($fornecedor instanceof JsonResponse) {
            return $fornecedor;
        }

        // Idempotente: o mesmo recibo do mesmo fornecedor nao entra duas vezes.
        $existente = PagamentoFornecedor::query()
            ->where('fornecedor_id', $fornecedor->id)
            ->where(function ($q) use ($data) {
                $temAlgo = false;

                if (! empty($data['referencia_externa'])) {
                    $q->orWhere('referencia_externa', $data['referencia_externa']);
                    $temAlgo = true;
                }

                if (! empty($data['numero_recibo'])) {
                    $q->orWhere('numero_recibo', $data['numero_recibo']);
                    $temAlgo = true;
                }

                if (! $temAlgo) {
                    $q->whereRaw('1 = 0');
                }
            })
            ->first();

        if ($existente) {
            return $this->ok([
                'pagamento' => $this->formatarPagamento($existente->load('despesas:id,numero_fatura')),
                'saldo' => $this->saldoDe($fornecedor),
                'ja_existia' => true,
            ], ['este recibo ja estava registado; nada foi alterado.']);
        }

        $faturas = collect($data['faturas'] ?? [])->map(function ($f) {
            if (is_array($f)) {
                return [
                    'despesa_id' => $f['id'] ?? $f['despesa_id'] ?? null,
                    'numero_fatura' => $f['numero_fatura'] ?? $f['numero'] ?? null,
                    'valor' => $f['valor'] ?? null,
                ];
            }

            return ['despesa_id' => null, 'numero_fatura' => (string) $f, 'valor' => null];
        })->all();

        $resultado = $this->conta->registarPagamento(
            $fornecedor,
            $data,
            $faturas,
            (bool) ($data['imputar_automaticamente'] ?? false)
        );

        return $this->criado([
            'pagamento' => $this->formatarPagamento($resultado['pagamento']),
            'saldo' => $this->saldoDe($fornecedor),
        ], $resultado['avisos']);
    }

    /** POST /pagamentos/{pagamento}/ficheiro — a foto do recibo, em multipart. */
    public function ficheiro(Request $request, PagamentoFornecedor $pagamento): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'ficheiro' => ['required', 'file', 'mimes:jpeg,jpg,png,webp,pdf', 'max:20480'],
        ]);

        if ($validator->fails()) {
            return $this->erro422($validator->errors()->toArray());
        }

        if ($pagamento->ficheiro_path) {
            Storage::disk('public')->delete($pagamento->ficheiro_path);
        }

        $pagamento->update([
            'ficheiro_path' => app(CompressorImagens::class)->guardar($request->file('ficheiro'), 'recibos'),
        ]);

        return $this->ok([
            'pagamento_id' => $pagamento->id,
            'ficheiro_url' => Storage::disk('public')->url($pagamento->ficheiro_path),
        ]);
    }

    /** DELETE /pagamentos/{pagamento} — anula um recibo mal registado. */
    public function destroy(PagamentoFornecedor $pagamento): JsonResponse
    {
        $fornecedor = $pagamento->fornecedor;
        $pagamento->delete();

        return $this->ok([
            'pagamento_id' => $pagamento->id,
            'apagado' => true,
            'saldo' => $fornecedor ? $this->saldoDe($fornecedor) : null,
        ]);
    }

    /** POST /pagamentos/{id}/restaurar */
    public function restore(int $id): JsonResponse
    {
        $pagamento = PagamentoFornecedor::withTrashed()->findOrFail($id);
        $pagamento->restore();

        return $this->ok(['pagamento' => $this->formatarPagamento($pagamento->load('despesas:id,numero_fatura'))]);
    }

    // ── Auxiliares ───────────────────────────────────────────────────────────

    private function resolverFornecedor(mixed $referencia): Fornecedor|JsonResponse
    {
        if (is_array($referencia)) {
            $referencia = $referencia['id'] ?? $referencia['nome'] ?? null;
        }

        if (is_numeric($referencia) && ($porId = Fornecedor::query()->find((int) $referencia))) {
            return $porId;
        }

        $texto = trim((string) $referencia);

        if ($texto !== '' && ($porNome = Fornecedor::paraNome($texto))) {
            return $porNome;
        }

        if ($texto !== '' && ($porNif = Fornecedor::query()->where('nif', preg_replace('/\D/', '', $texto))->first())) {
            return $porNif;
        }

        $parecidos = Fornecedor::query()
            ->where('nome', 'like', '%'.(preg_split('/\s+/u', $texto)[0] ?? $texto).'%')
            ->limit(6)
            ->get(['id', 'nome', 'nif']);

        $mensagens = ["Fornecedor nao encontrado: {$texto}."];

        if ($parecidos->isNotEmpty()) {
            $mensagens[] = ['valor' => $texto, 'candidatos' => $parecidos->toArray()];
        }

        return $this->erro422(['fornecedor' => $mensagens]);
    }

    private function saldoDe(Fornecedor $fornecedor): array
    {
        $s = $this->conta->saldos()->firstWhere('id', $fornecedor->id);

        return [
            'fornecedor' => $fornecedor->nome,
            'em_divida' => $s['saldo'] ?? 0.0,
            'por_imputar' => $s['por_imputar'] ?? 0.0,
            'faturas_em_aberto' => $s['faturas_em_aberto'] ?? 0,
        ];
    }

    private function formatarPagamento(PagamentoFornecedor $p): array
    {
        return [
            'id' => $p->id,
            'fornecedor_id' => $p->fornecedor_id,
            'data' => $p->data?->format('Y-m-d'),
            'valor' => round((float) $p->valor, 2),
            'numero_recibo' => $p->numero_recibo,
            'metodo' => $p->metodo,
            'faturas' => $p->despesas->map(fn ($d) => [
                'id' => $d->id,
                'numero_fatura' => $d->numero_fatura,
                'valor' => round((float) $d->pivot->valor, 2),
            ])->values()->all(),
            'faturas_pendentes' => $p->faturas_pendentes ?? [],
            'por_imputar' => $p->valor_por_imputar,
            'ficheiro_url' => $p->ficheiro_path ? Storage::disk('public')->url($p->ficheiro_path) : null,
        ];
    }
}
