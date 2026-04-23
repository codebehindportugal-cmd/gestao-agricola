<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('maquinas', function (Blueprint $table) {
            $table->id();
            $table->string('nome');
            $table->string('tipo')->comment('trator, ceifeira, carrinha, etc');
            $table->string('marca')->nullable();
            $table->string('modelo')->nullable();
            $table->string('matricula')->nullable()->unique();
            $table->string('numero_serie')->nullable()->unique();
            $table->year('ano_aquisicao')->nullable();
            $table->decimal('horas_uso', 10, 2)->default(0);
            $table->decimal('horas_manutencao', 10, 2)->nullable()->comment('próxima manutenção em horas');
            $table->enum('estado', ['operacional', 'em_manutencao', 'danificada', 'retirada'])->default('operacional');
            $table->text('observacoes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('maquinas');
    }
};
