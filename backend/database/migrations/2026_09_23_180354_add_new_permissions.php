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
                'name' => 'invoices.download_report',
                'display_name' => 'تحميل تقرير مجمع للموردين',
                'section' => 'invoices',
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
            'invoices.download_report',
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
