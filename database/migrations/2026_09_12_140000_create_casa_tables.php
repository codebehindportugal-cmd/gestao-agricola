<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Último estado conhecido de cada dispositivo da casa.
        // O Home Assistant empurra: o site nunca interroga a casa.
        Schema::create('casa_dispositivos', function (Blueprint $table) {
            $table->id();

            // entity_id do Home Assistant, ex.: binary_sensor.movimento_sala
            $table->string('entity_id')->unique();
            $table->string('nome');
            $table->string('zona')->nullable();

            // movimento | porta | janela | agua | fumo | temperatura | humidade | outro
            $table->string('tipo');
            $table->string('estado')->nullable();
            $table->json('atributos')->nullable();

            $table->timestamp('visto_em')->nullable();
            $table->boolean('visivel')->default(true);
            $table->unsignedSmallInteger('ordem')->default(0);

            $table->timestamps();

            $table->index(['visivel', 'ordem']);
            $table->index('zona');
        });

        // Histórico de accionamentos. Só o que é evento, não leituras contínuas.
        Schema::create('casa_eventos', function (Blueprint $table) {
            $table->id();

            $table->string('entity_id');
            $table->string('nome')->nullable();
            $table->string('zona')->nullable();
            $table->string('tipo');
            $table->string('estado');
            $table->timestamp('ocorreu_em');

            $table->timestamps();

            $table->index(['ocorreu_em', 'tipo']);
            $table->index('entity_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('casa_eventos');
        Schema::dropIfExists('casa_dispositivos');
    }
};
