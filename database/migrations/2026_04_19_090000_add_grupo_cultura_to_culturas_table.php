<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('culturas', function (Blueprint $table) {
            $table->string('grupo_cultura')->default('outro')->after('nome');
        });
    }

    public function down(): void
    {
        Schema::table('culturas', function (Blueprint $table) {
            $table->dropColumn('grupo_cultura');
        });
    }
};
