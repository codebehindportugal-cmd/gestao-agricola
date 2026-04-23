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
        Schema::create('equipa_funcionario', function (Blueprint $table) {
            $table->id();
            $table->foreignId('equipa_id')->constrained('equipas')->cascadeOnDelete();
            $table->foreignId('funcionario_id')->constrained('funcionarios')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['equipa_id', 'funcionario_id']);
            $table->index('equipa_id');
            $table->index('funcionario_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('equipa_funcionario');
    }
};
