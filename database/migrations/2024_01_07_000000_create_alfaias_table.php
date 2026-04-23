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
        Schema::create('alfaias', function (Blueprint $table) {
            $table->id();
            $table->string('nome');
            $table->string('tipo')->comment('charrua, grade de discos, pulverizador, etc');
            $table->foreignId('maquina_id')->nullable()->constrained('maquinas')->setOnDelete('set null');
            $table->text('descricao')->nullable();
            $table->decimal('comprimento', 5, 2)->nullable();
            $table->decimal('largura', 5, 2)->nullable();
            $table->enum('estado', ['operacional', 'danificada', 'retirada'])->default('operacional');
            $table->text('observacoes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('maquina_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('alfaias');
    }
};
