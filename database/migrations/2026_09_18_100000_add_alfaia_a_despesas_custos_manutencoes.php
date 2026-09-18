<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Alfaias com vida propria: pecas e manutencoes que sao delas, nao do trator.
 *
 * Um radiador comprado para o triturador "Amanha de pes" era registado no
 * Hurlimann que o puxa, porque nao havia outro sitio onde o por: a fatura so
 * aceitava `maquina` e uma manutencao exigia maquina_id. O desgaste da alfaia
 * ficava escondido dentro do custo/hora do trator e nunca se podia perguntar
 * quanto custou a alfaia no ano.
 *
 * Passa a haver alfaia_id em despesas, custos e manutencoes. Em manutencoes,
 * maquina_id fica nullable - uma revisao pode ser so da alfaia. A regra "tem
 * de ter maquina ou alfaia" fica na validacao da aplicacao, e nao numa
 * constraint, para o MySQL 5.7 do servidor nao ser um problema.
 *
 * despesas leva tambem maquina_id: ate aqui so o Custo sabia a que maquina
 * pertencia a fatura, e o ecra das despesas nao tinha como mostra-lo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('despesas', function (Blueprint $table) {
            if (! Schema::hasColumn('despesas', 'maquina_id')) {
                $table->foreignId('maquina_id')
                    ->nullable()
                    ->after('campanha_id')
                    ->constrained('maquinas')
                    ->nullOnDelete();
            }
        });

        Schema::table('despesas', function (Blueprint $table) {
            if (! Schema::hasColumn('despesas', 'alfaia_id')) {
                $table->foreignId('alfaia_id')
                    ->nullable()
                    ->after('maquina_id')
                    ->constrained('alfaias')
                    ->nullOnDelete();
            }
        });

        Schema::table('custos', function (Blueprint $table) {
            if (! Schema::hasColumn('custos', 'alfaia_id')) {
                $table->foreignId('alfaia_id')
                    ->nullable()
                    ->after('maquina_id')
                    ->constrained('alfaias')
                    ->nullOnDelete();
            }
        });

        // A alteracao vem antes de acrescentar alfaia_id de proposito: no
        // SQLite dos testes um change() reconstroi a tabela inteira, e ha
        // menos para reconstruir se a coluna nova ainda nao la estiver.
        // maquina_id fica unsigned bigint nullable - nao tinha default nem
        // comment, por isso o change() nao descarta nada.
        Schema::table('manutencoes', function (Blueprint $table) {
            $table->foreignId('maquina_id')->nullable()->change();
        });

        Schema::table('manutencoes', function (Blueprint $table) {
            if (! Schema::hasColumn('manutencoes', 'alfaia_id')) {
                $table->foreignId('alfaia_id')
                    ->nullable()
                    ->after('maquina_id')
                    ->constrained('alfaias')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        foreach (['manutencoes', 'custos', 'despesas'] as $tabela) {
            Schema::table($tabela, function (Blueprint $table) use ($tabela) {
                if (Schema::hasColumn($tabela, 'alfaia_id')) {
                    $table->dropConstrainedForeignId('alfaia_id');
                }
            });
        }

        Schema::table('despesas', function (Blueprint $table) {
            if (Schema::hasColumn('despesas', 'maquina_id')) {
                $table->dropConstrainedForeignId('maquina_id');
            }
        });

        // maquina_id fica nullable de proposito: voltar a obriga-la era apagar
        // as manutencoes que so tem alfaia, que sao precisamente as que esta
        // migracao veio permitir.
    }
};
