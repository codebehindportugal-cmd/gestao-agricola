<?php

use App\Models\Fornecedor;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Conta corrente dos fornecedores.
 *
 * Ate aqui as faturas de compra guardavam o fornecedor so como texto e nao
 * havia sitio para os pagamentos: nunca se sabia quanto se devia a quem.
 *
 * - despesas.fornecedor_id liga a fatura a ficha do fornecedor (o texto fica,
 *   e o que veio impresso na fatura);
 * - despesas.pago_no_ato marca as faturas-recibo e simplificadas, que ja vem
 *   pagas e nao entram na divida;
 * - pagamentos_fornecedores e o recibo que o fornecedor manda;
 * - pagamento_fornecedor_despesa diz que faturas esse recibo pagou e quanto.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('despesas', function (Blueprint $table) {
            if (! Schema::hasColumn('despesas', 'fornecedor_id')) {
                $table->foreignId('fornecedor_id')
                    ->nullable()
                    ->after('fornecedor')
                    ->constrained('fornecedores')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('despesas', 'pago_no_ato')) {
                $table->boolean('pago_no_ato')->default(false)->after('valor');
            }
        });

        if (! Schema::hasTable('pagamentos_fornecedores')) {
            Schema::create('pagamentos_fornecedores', function (Blueprint $table) {
                $table->id();
                $table->foreignId('fornecedor_id')->constrained('fornecedores')->cascadeOnDelete();
                $table->date('data');
                $table->decimal('valor', 12, 2);
                $table->string('numero_recibo', 100)->nullable();
                $table->string('metodo', 30)->nullable();
                $table->string('ficheiro_path')->nullable();
                // Numeros de faturas que o recibo diz pagar mas que ainda nao
                // estao registadas: ligam-se sozinhas quando a fatura entrar.
                $table->json('faturas_pendentes')->nullable();
                $table->text('notas')->nullable();
                $table->string('referencia_externa')->nullable()->index();
                $table->timestamps();
                $table->softDeletes();

                $table->index(['fornecedor_id', 'data']);
            });
        }

        if (! Schema::hasTable('pagamento_fornecedor_despesa')) {
            Schema::create('pagamento_fornecedor_despesa', function (Blueprint $table) {
                $table->id();
                $table->foreignId('pagamento_fornecedor_id')->constrained('pagamentos_fornecedores')->cascadeOnDelete();
                $table->foreignId('despesa_id')->constrained('despesas')->cascadeOnDelete();
                $table->decimal('valor', 12, 2);
                $table->timestamps();

                $table->unique(['pagamento_fornecedor_id', 'despesa_id'], 'pagamento_despesa_unico');
            });
        }

        $this->ligarFaturasAosFornecedores();
        $this->marcarPagasNoAto();
    }

    public function down(): void
    {
        Schema::dropIfExists('pagamento_fornecedor_despesa');
        Schema::dropIfExists('pagamentos_fornecedores');

        Schema::table('despesas', function (Blueprint $table) {
            if (Schema::hasColumn('despesas', 'fornecedor_id')) {
                $table->dropConstrainedForeignId('fornecedor_id');
            }

            if (Schema::hasColumn('despesas', 'pago_no_ato')) {
                $table->dropColumn('pago_no_ato');
            }
        });
    }

    /**
     * Cada nome de fornecedor escrito nas faturas passa a ter ficha. Nomes que
     * so diferem em maiusculas, acentos ou "Lda" ficam no mesmo fornecedor.
     */
    private function ligarFaturasAosFornecedores(): void
    {
        $nomes = DB::table('despesas')
            ->whereNull('fornecedor_id')
            ->whereNotNull('fornecedor')
            ->where('fornecedor', '!=', '')
            ->distinct()
            ->pluck('fornecedor');

        foreach ($nomes as $nome) {
            $fornecedor = Fornecedor::paraNome($nome, criar: true);

            if ($fornecedor) {
                DB::table('despesas')
                    ->whereNull('fornecedor_id')
                    ->where('fornecedor', $nome)
                    ->update(['fornecedor_id' => $fornecedor->id]);
            }
        }
    }

    private function marcarPagasNoAto(): void
    {
        DB::table('despesas')
            ->whereNotNull('numero_fatura')
            ->select(['id', 'numero_fatura'])
            ->orderBy('id')
            ->each(function ($despesa) {
                if (\App\Models\Despesa::numeroEhPagoNoAto($despesa->numero_fatura)) {
                    DB::table('despesas')->where('id', $despesa->id)->update(['pago_no_ato' => true]);
                }
            });
    }
};
