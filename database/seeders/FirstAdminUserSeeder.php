<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class FirstAdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(RolePermissionSeeder::class);

        $email = env('FIRST_ADMIN_EMAIL', 'andre_mendes_92@hotmail.com');
        $name = env('FIRST_ADMIN_NAME', 'Andre Mendes');
        $password = env('FIRST_ADMIN_PASSWORD');

        $user = User::query()->firstOrNew(['email' => $email]);

        if (! $user->exists && blank($password)) {
            throw new RuntimeException('Defina FIRST_ADMIN_PASSWORD no .env antes de criar a primeira conta admin.');
        }

        $user->name = $user->name ?: $name;

        if (filled($password)) {
            $user->password = Hash::make($password);
        }

        $user->save();

        $adminRole = Role::query()->firstOrCreate(
            ['name' => 'admin'],
            ['description' => 'Administrador - Acesso total ao sistema'],
        );

        $user->roles()->syncWithoutDetaching($adminRole);
    }
}
