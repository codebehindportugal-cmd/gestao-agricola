<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('exploracao_settings')) {
            Schema::create('exploracao_settings', function (Blueprint $table) {
                $table->id();
                $table->string('produtor_nome')->nullable();
                $table->string('concelho')->nullable();
                $table->string('freguesia')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('estabelecimentos_venda')) {
            Schema::create('estabelecimentos_venda', function (Blueprint $table) {
                $table->id();
                $table->string('nome');
                $table->string('numero_autorizacao')->nullable();
                $table->boolean('ativo')->default(true);
                $table->timestamps();
            });
        }

        Schema::table('funcionarios', function (Blueprint $table) {
            if (! Schema::hasColumn('funcionarios', 'aplicador_numero_autorizacao')) {
                $table->string('aplicador_numero_autorizacao')->nullable()->after('cargo');
            }
        });

        Schema::table('produtos', function (Blueprint $table) {
            if (! Schema::hasColumn('produtos', 'estabelecimento_venda_id')) {
                $table->foreignId('estabelecimento_venda_id')
                    ->nullable()
                    ->after('estabelecimento_venda_autorizacao')
                    ->constrained('estabelecimentos_venda')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('produtos', function (Blueprint $table) {
            if (Schema::hasColumn('produtos', 'estabelecimento_venda_id')) {
                $table->dropConstrainedForeignId('estabelecimento_venda_id');
            }
        });

        Schema::table('funcionarios', function (Blueprint $table) {
            if (Schema::hasColumn('funcionarios', 'aplicador_numero_autorizacao')) {
                $table->dropColumn('aplicador_numero_autorizacao');
            }
        });

        Schema::dropIfExists('estabelecimentos_venda');
        Schema::dropIfExists('exploracao_settings');
    }
};
