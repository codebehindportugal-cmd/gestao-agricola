<?php

namespace App\Http\Controllers;

use App\Models\Permission;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PermissionController extends Controller
{
    public function index(): Response
    {
        $permissions = Permission::paginate(10);

        return Inertia::render('Permissions/Index', [
            'permissions' => $permissions,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Permissions/Create');
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'name' => 'required|string|max:255|unique:permissions',
            'description' => 'nullable|string|max:255',
        ]);

        try {
            Permission::create([
                'name' => $request->name,
                'description' => $request->description,
            ]);
        } catch (\Throwable $exception) {
            return $this->backWithError('Não foi possível criar a permissão. Verifique os dados e tente novamente.', $exception);
        }

        return redirect()->route('permissions.index')->with('success', 'Permission created successfully.');
    }

    public function edit(Permission $permission): Response
    {
        return Inertia::render('Permissions/Edit', [
            'permission' => $permission,
        ]);
    }

    public function update(Request $request, Permission $permission): RedirectResponse
    {
        $request->validate([
            'name' => 'required|string|max:255|unique:permissions,name,' . $permission->id,
            'description' => 'nullable|string|max:255',
        ]);

        try {
            $permission->update([
                'name' => $request->name,
                'description' => $request->description,
            ]);
        } catch (\Throwable $exception) {
            return $this->backWithError('Não foi possível atualizar a permissão. Verifique os dados e tente novamente.', $exception);
        }

        return redirect()->route('permissions.index')->with('success', 'Permission updated successfully.');
    }

    public function destroy(Permission $permission): RedirectResponse
    {
        try {
            $permission->delete();
        } catch (\Throwable $exception) {
            return $this->backWithError('Não foi possível remover a permissão. Confirme se não existem perfis de acesso associados.', $exception);
        }

        return redirect()->route('permissions.index')->with('success', 'Permission deleted successfully.');
    }
}
