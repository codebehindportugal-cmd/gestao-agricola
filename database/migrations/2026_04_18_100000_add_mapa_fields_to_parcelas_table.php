<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('parcelas', function (Blueprint $table) {
            $table->decimal('latitude', 10, 8)->nullable()->after('estado');
            $table->decimal('longitude', 11, 8)->nullable()->after('latitude');
            $table->json('poligono')->nullable()->after('longitude');
        });
    }

    public function down(): void
    {
        Schema::table('parcelas', function (Blueprint $table) {
            $table->dropColumn(['latitude', 'longitude', 'poligono']);
        });
    }
};
