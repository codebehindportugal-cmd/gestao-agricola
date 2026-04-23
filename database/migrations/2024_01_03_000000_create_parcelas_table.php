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
        Schema::create('parcelas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('terreno_id')->constrained('terrenos')->cascadeOnDelete();
            $table->string('nome');
            $table->string('numero_parcela')->nullable();
            $table->decimal('area_total', 10, 2);
            $table->decimal('area_util', 10, 2)->nullable();
            $table->text('descricao')->nullable();
            $table->enum('estado', ['livre', 'cultivada', 'em_preparacao', 'pousio'])->default('livre');
            $table->timestamps();
            $table->softDeletes();

            $table->index('terreno_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('parcelas');
    }
};
