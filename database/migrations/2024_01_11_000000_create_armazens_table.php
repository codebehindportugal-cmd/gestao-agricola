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
        Schema::create('armazens', function (Blueprint $table) {
            $table->id();
            $table->string('nome');
            $table->string('tipo')->comment('frigorifico, seco, etc');
            $table->string('localizacao')->nullable();
            $table->decimal('capacidade', 10, 2)->nullable()->comment('em toneladas');
            $table->decimal('area', 10, 2)->nullable()->comment('em m2');
            $table->text('observacoes')->nullable();
            $table->enum('status', ['operacional', 'manutencao', 'inativo'])->default('operacional');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('armazens');
    }
};
