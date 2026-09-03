<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Registo dos avisos ntfy ja enviados por compromisso.
 *
 * A chave unica (compromisso_id, dias_antes) e o que impede que a tarefa
 * diaria repita o mesmo aviso todos os dias.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('compromisso_avisos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('compromisso_id')->constrained('compromissos')->cascadeOnDelete();
            // 30, 7, 1 = dias antes do prazo; -1 = aviso de atraso.
            $table->integer('dias_antes');
            $table->timestamp('enviado_em');
            $table->timestamps();

            $table->unique(['compromisso_id', 'dias_antes']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('compromisso_avisos');
    }
};
