<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    public function up(): void
    {
        $permissions = [
            [
                'name' => 'products.download_kg_products',
                'display_name' => 'تحميل منتجات الميزان',
                'section' => 'products',
            ],

        ];

        $admin = Role::where('name', 'admin')
            ->where('guard_name', 'web')
            ->first();

        foreach ($permissions as $data) {
            $permission = Permission::firstOrCreate(
                [
                    'name' => $data['name'],
                    'guard_name' => 'web',
                ],
                [
                    'display_name' => $data['display_name'],
                    'section' => $data['section'],
                ]
            );

            if ($admin) {
                $admin->givePermissionTo($permission);
            }
        }
    }

    public function down(): void
    {
        $permissionNames = [
            'products.download_kg_products',
        ];

        $admin = Role::where('name', 'admin')
            ->where('guard_name', 'web')
            ->first();

        if ($admin) {
            $permissions = Permission::whereIn('name', $permissionNames)
                ->where('guard_name', 'web')
                ->get();

            foreach ($permissions as $permission) {
                $admin->revokePermissionTo($permission);
            }
        }

        Permission::whereIn('name', $permissionNames)
            ->where('guard_name', 'web')
            ->delete();
    }
};
