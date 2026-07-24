<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('despesas', function (Blueprint $table) {
            if (! Schema::hasColumn('despesas', 'campanha_id')) {
                $table->foreignId('campanha_id')
                    ->nullable()
                    ->after('data')
                    ->constrained('campanhas')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('despesas', function (Blueprint $table) {
            if (Schema::hasColumn('despesas', 'campanha_id')) {
                $table->dropConstrainedForeignId('campanha_id');
            }
        });
    }
};
