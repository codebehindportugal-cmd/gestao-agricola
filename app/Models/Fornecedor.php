<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Fornecedor extends Model
{
    use SoftDeletes;

    protected $table = 'fornecedores';

    protected $fillable = [
        'nome',
        'contacto',
        'email',
        'telefone',
        'localizacao',
        'nif',
        'observacoes',
        'status',
    ];

    /** Palavras que nao distinguem um fornecedor de outro ("Casa Queridos, Lda"). */
    private const SUFIXOS = ['lda', 'limitada', 'unipessoal', 'unip', 'sa', 'slu', 'sl', 'unipess'];

    // Relacionamentos
    public function produtos(): HasMany
    {
        return $this->hasMany(Produto::class);
    }

    /** Faturas de compra deste fornecedor. */
    public function despesas(): HasMany
    {
        return $this->hasMany(Despesa::class);
    }

    /** Recibos: o que ja se lhe pagou. */
    public function pagamentos(): HasMany
    {
        return $this->hasMany(PagamentoFornecedor::class);
    }

    /**
     * Chave de comparacao do nome: sem acentos, maiusculas, pontuacao nem
     * "Lda". "CASA QUERIDOS, LDA." e "Casa Queridos" dao o mesmo.
     */
    public static function normalizarNome(?string $nome): string
    {
        $texto = Str::lower(Str::ascii((string) $nome));
        $texto = preg_replace('/[^a-z0-9]+/', ' ', $texto) ?? '';
        $palavras = array_filter(
            explode(' ', $texto),
            fn (string $p) => $p !== '' && ! in_array($p, self::SUFIXOS, true)
        );

        return implode(' ', $palavras);
    }

    /**
     * Fornecedor com este nome (comparado pela chave normalizada), ou null.
     * Com $criar, cria a ficha quando ainda nao existe.
     */
    public static function paraNome(?string $nome, bool $criar = false): ?self
    {
        $chave = self::normalizarNome($nome);

        if ($chave === '') {
            return null;
        }

        $existente = self::query()
            ->get(['id', 'nome'])
            ->first(fn (self $f) => self::normalizarNome($f->nome) === $chave);

        if ($existente) {
            return self::query()->find($existente->id);
        }

        if (! $criar) {
            return null;
        }

        return self::query()->create([
            'nome' => trim((string) $nome),
            'status' => 'ativo',
        ]);
    }
}
