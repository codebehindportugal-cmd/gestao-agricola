<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('custos', function (Blueprint $table) {
            if (! Schema::hasColumn('custos', 'campanha_id')) {
                $table->foreignId('campanha_id')
                    ->nullable()
                    ->after('operacao_id')
                    ->constrained('campanhas')
                    ->nullOnDelete();
            }
        });

        Schema::table('produtos', function (Blueprint $table) {
            if (! Schema::hasColumn('produtos', 'numero_autorizacao_dgav')) {
                $table->string('numero_autorizacao_dgav')->nullable()->after('codigo_interno');
            }
        });

        Schema::table('operacoes', function (Blueprint $table) {
            if (! Schema::hasColumn('operacoes', 'produtor_nome')) {
                $table->string('produtor_nome')->nullable()->after('equipa_id');
            }

            if (! Schema::hasColumn('operacoes', 'aplicador_nome')) {
                $table->string('aplicador_nome')->nullable()->after('produtor_nome');
            }

            if (! Schema::hasColumn('operacoes', 'aplicador_numero_autorizacao')) {
                $table->string('aplicador_numero_autorizacao')->nullable()->after('aplicador_nome');
            }

            if (! Schema::hasColumn('operacoes', 'exploracao_concelho')) {
                $table->string('exploracao_concelho')->nullable()->after('aplicador_numero_autorizacao');
            }

            if (! Schema::hasColumn('operacoes', 'exploracao_freguesia')) {
                $table->string('exploracao_freguesia')->nullable()->after('exploracao_concelho');
            }
        });

        Schema::table('operacao_produtos', function (Blueprint $table) {
            if (! Schema::hasColumn('operacao_produtos', 'estabelecimento_venda_nome')) {
                $table->string('estabelecimento_venda_nome')->nullable()->after('intervalo_seguranca_dias');
            }

            if (! Schema::hasColumn('operacao_produtos', 'estabelecimento_venda_autorizacao')) {
                $table->string('estabelecimento_venda_autorizacao')->nullable()->after('estabelecimento_venda_nome');
            }
        });
    }

    public function down(): void
    {
        Schema::table('operacao_produtos', function (Blueprint $table) {
            foreach (['estabelecimento_venda_autorizacao', 'estabelecimento_venda_nome'] as $column) {
                if (Schema::hasColumn('operacao_produtos', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('operacoes', function (Blueprint $table) {
            foreach ([
                'exploracao_freguesia',
                'exploracao_concelho',
                'aplicador_numero_autorizacao',
                'aplicador_nome',
                'produtor_nome',
            ] as $column) {
                if (Schema::hasColumn('operacoes', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('produtos', function (Blueprint $table) {
            if (Schema::hasColumn('produtos', 'numero_autorizacao_dgav')) {
                $table->dropColumn('numero_autorizacao_dgav');
            }
        });

        Schema::table('custos', function (Blueprint $table) {
            if (Schema::hasColumn('custos', 'campanha_id')) {
                $table->dropConstrainedForeignId('campanha_id');
            }
        });
    }
};
