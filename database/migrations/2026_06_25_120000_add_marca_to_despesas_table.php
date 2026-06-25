<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('despesas', function (Blueprint $table) {
            if (! Schema::hasColumn('despesas', 'marca')) {
                $table->string('marca')->default('horta_da_maria')->after('categoria');
            }
        });
    }

    public function down(): void
    {
        Schema::table('despesas', function (Blueprint $table) {
            if (Schema::hasColumn('despesas', 'marca')) {
                $table->dropColumn('marca');
            }
        });
    }
};
