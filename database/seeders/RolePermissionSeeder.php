<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Database\Seeder;

class RolePermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Criar Roles
        $adminRole = Role::firstOrCreate(
            ['name' => 'admin'],
            ['description' => 'Administrador - Acesso total ao sistema']
        );

        $gestorRole = Role::firstOrCreate(
            ['name' => 'gestor_agricola'],
            ['description' => 'Gestor Agrícola - Planeamento, acompanhamento e relatórios']
        );

        $operadorRole = Role::firstOrCreate(
            ['name' => 'operador'],
            ['description' => 'Operador - Registo de operações no terreno']
        );

        $armazemRole = Role::firstOrCreate(
            ['name' => 'armazem'],
            ['description' => 'Gestor de Armazém - Gestão de entradas e saídas']
        );

        $consultorRole = Role::firstOrCreate(
            ['name' => 'consultor'],
            ['description' => 'Consultor/Técnico - Visualização e apoio técnico']
        );

        // Criar Permissões
        $permissions = [
            // Terrenos
            ['name' => 'terrenos.view', 'description' => 'Ver terrenos'],
            ['name' => 'terrenos.create', 'description' => 'Criar terrenos'],
            ['name' => 'terrenos.edit', 'description' => 'Editar terrenos'],
            ['name' => 'terrenos.delete', 'description' => 'Eliminar terrenos'],

            // Parcelas
            ['name' => 'parcelas.view', 'description' => 'Ver parcelas'],
            ['name' => 'parcelas.create', 'description' => 'Criar parcelas'],
            ['name' => 'parcelas.edit', 'description' => 'Editar parcelas'],
            ['name' => 'parcelas.delete', 'description' => 'Eliminar parcelas'],

            // Culturas
            ['name' => 'culturas.view', 'description' => 'Ver culturas'],
            ['name' => 'culturas.create', 'description' => 'Criar culturas'],
            ['name' => 'culturas.edit', 'description' => 'Editar culturas'],
            ['name' => 'culturas.delete', 'description' => 'Eliminar culturas'],

            // Operações
            ['name' => 'operacoes.view', 'description' => 'Ver operações'],
            ['name' => 'operacoes.create', 'description' => 'Criar operações'],
            ['name' => 'operacoes.edit', 'description' => 'Editar operações'],
            ['name' => 'operacoes.delete', 'description' => 'Eliminar operações'],

            // Máquinas
            ['name' => 'maquinas.view', 'description' => 'Ver máquinas'],
            ['name' => 'maquinas.create', 'description' => 'Criar máquinas'],
            ['name' => 'maquinas.edit', 'description' => 'Editar máquinas'],
            ['name' => 'maquinas.delete', 'description' => 'Eliminar máquinas'],

            // Relatórios
            ['name' => 'relatorios.view', 'description' => 'Ver relatórios'],
            ['name' => 'relatorios.export', 'description' => 'Exportar relatórios'],

            // Utilizadores
            ['name' => 'usuarios.manage', 'description' => 'Gerir utilizadores'],
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission['name']], $permission);
        }

        // Atribuir Permissions aos Roles
        // Admin tem todas as permissions
        $adminRole->permissions()->sync(
            Permission::all()->pluck('id')
        );

        // Gestor Agrícola
        $gestorPermissions = Permission::whereIn('name', [
            'terrenos.view', 'terrenos.create', 'terrenos.edit',
            'parcelas.view', 'parcelas.create', 'parcelas.edit',
            'culturas.view', 'culturas.create', 'culturas.edit',
            'operacoes.view', 'operacoes.create', 'operacoes.edit',
            'maquinas.view', 'maquinas.create', 'maquinas.edit',
            'relatorios.view', 'relatorios.export',
        ])->pluck('id');
        $gestorRole->permissions()->sync($gestorPermissions);

        // Operador
        $operadorPermissions = Permission::whereIn('name', [
            'operacoes.view', 'operacoes.create', 'operacoes.edit',
            'maquinas.view',
            'relatorios.view',
        ])->pluck('id');
        $operadorRole->permissions()->sync($operadorPermissions);

        // Armazém
        $armazemPermissions = Permission::whereIn('name', [
            'relatorios.view', 'relatorios.export',
        ])->pluck('id');
        $armazemRole->permissions()->sync($armazemPermissions);

        // Consultor
        $consultorPermissions = Permission::whereIn('name', [
            'terrenos.view',
            'parcelas.view',
            'culturas.view',
            'operacoes.view',
            'maquinas.view',
            'relatorios.view',
        ])->pluck('id');
        $consultorRole->permissions()->sync($consultorPermissions);

        if (! User::whereHas('roles', fn ($query) => $query->where('name', 'admin'))->exists()) {
            $firstUser = User::query()->orderBy('id')->first();

            if ($firstUser) {
                $firstUser->roles()->syncWithoutDetaching($adminRole);
            }
        }
    }
}
