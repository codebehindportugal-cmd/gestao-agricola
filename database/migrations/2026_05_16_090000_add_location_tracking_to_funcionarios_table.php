<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('funcionarios', function (Blueprint $table) {
            $table->string('location_token', 80)->nullable()->unique()->after('observacoes');
            $table->decimal('last_latitude', 10, 7)->nullable()->after('location_token');
            $table->decimal('last_longitude', 10, 7)->nullable()->after('last_latitude');
            $table->unsignedInteger('last_accuracy')->nullable()->after('last_longitude');
            $table->decimal('last_speed', 8, 2)->nullable()->after('last_accuracy');
            $table->decimal('last_heading', 6, 2)->nullable()->after('last_speed');
            $table->timestamp('location_shared_at')->nullable()->after('last_heading');
            $table->timestamp('location_token_refreshed_at')->nullable()->after('location_shared_at');

            $table->index('location_shared_at');
        });

        DB::table('funcionarios')
            ->whereNull('location_token')
            ->orderBy('id')
            ->each(function ($funcionario) {
                DB::table('funcionarios')
                    ->where('id', $funcionario->id)
                    ->update([
                        'location_token' => Str::random(48),
                        'location_token_refreshed_at' => now(),
                    ]);
            });
    }

    public function down(): void
    {
        Schema::table('funcionarios', function (Blueprint $table) {
            $table->dropIndex(['location_shared_at']);
            $table->dropUnique(['location_token']);
            $table->dropColumn([
                'location_token',
                'last_latitude',
                'last_longitude',
                'last_accuracy',
                'last_speed',
                'last_heading',
                'location_shared_at',
                'location_token_refreshed_at',
            ]);
        });
    }
};
