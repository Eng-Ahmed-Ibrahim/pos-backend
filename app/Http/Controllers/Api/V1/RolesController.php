<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RolesController extends Controller
{
    // GET ALL ROLES + PERMISSIONS
    public function index()
    {
        $roles = Role::with('permissions')
            ->orderBy('id', 'desc')
            ->get();

        $permissions = Permission::orderBy('id', 'desc')->get();

        return response()->json([
            "status" => true,
            "data" => [
                "roles" => $roles,
                "permissions" => $permissions
            ]
        ]);
    }

    // SHOW SINGLE ROLE
    public function show($id)
    {
        $role = Role::with('permissions')->findOrFail($id);

        return response()->json([
            "status" => true,
            "data" => $role
        ]);
    }

    // CREATE ROLE
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|unique:roles,name',
            'permissions' => 'array',
            'permissions.*' => 'exists:permissions,name',
        ]);

        $role = Role::create([
            'name' => $request->name,
            'guard_name' => 'web',
        ]);

        // assign permissions
        if ($request->permissions) {
            $role->syncPermissions($request->permissions);
        }

        return response()->json([
            "status" => true,
            "message" => "Role created successfully",
            "data" => $role->load('permissions')
        ]);
    }

    // UPDATE ROLE
    public function update(Request $request, $id)
    {
        // 🔒 Protect the admin role (id = 1) from being modified
        if ((int) $id === 1) {
            return response()->json([
                "status" => false,
                "message" => "لا يمكن تعديل دور المدير الأساسي"
            ], 403);
        }

        $role = Role::findOrFail($id);

        $request->validate([
            'name' => 'required|unique:roles,name,' . $id,
            'permissions' => 'array',
            'permissions.*' => 'exists:permissions,name',
        ]);

        $role->update([
            'name' => $request->name,
        ]);

        // sync permissions (important)
        $role->syncPermissions($request->permissions ?? []);

        return response()->json([
            "status" => true,
            "message" => "Role updated successfully",
            "data" => $role->load('permissions')
        ]);
    }

    // DELETE ROLE
    public function destroy($id)
    {
        if ((int) $id === 1) {
            return response()->json([
                "status" => false,
                "message" => "لا يمكن حذف دور المدير الأساسي"
            ], 403);
        }

        $role = Role::findOrFail($id);

        $role->delete();

        return response()->json([
            "status" => true,
            "message" => "Role deleted successfully"
        ]);
    }
}