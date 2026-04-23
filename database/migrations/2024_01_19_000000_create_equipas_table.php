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
        Schema::create('equipas', function (Blueprint $table) {
            $table->id();
            $table->string('nome');
            $table->foreignId('lider_id')->nullable()->constrained('funcionarios')->setOnDelete('set null');
            $table->text('descricao')->nullable();
            $table->enum('status', ['ativa', 'inativa'])->default('ativa');
            $table->timestamps();
            $table->softDeletes();

            $table->index('lider_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('equipas');
    }
};
