<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $permissions = [
            "dashboard" => [
                [
                    "name" => "view",
                    "display_name" => "عرض لوحه التحكم",
                ]
            ],
            "point_of_sale" => [
                [
                    "name" => "view",
                    "display_name" => "عرض نقاط البيع",
                ],
                [
                    "name" => "create",
                    "display_name" => "إنشاء عملية بيع",
                ],
                [
                    "name" => "return",
                    "display_name" => "إرجاع مبيعات",
                ],
            ],
            "invoices" => [
                [
                    "name" => "view",
                    "display_name" => "عرض الفواتير",
                ],
                [
                    "name" => "create",
                    "display_name" => "إنشاء فاتوره",
                ],
                [
                    "name" => "delete",
                    "display_name" => "حذف فاتوره",
                ],
                [
                    "name" => "edit",
                    "display_name" => "تعديل فاتوره",
                ],
            ],

            "suppliers" => [
                [
                    "name" => "view",
                    "display_name" => "عرض الموردين",
                ],
                [
                    "name" => "create",
                    "display_name" => "إنشاء مورد",
                ],
                [
                    "name" => "delete",
                    "display_name" => "حذف مورد",
                ],
                [
                    "name" => "edit",
                    "display_name" => "تعديل مورد",
                ],
            ],

            "products" => [
                [
                    "name" => "view",
                    "display_name" => "عرض المنتجات",
                ],
                [
                    "name" => "create",
                    "display_name" => "إنشاء منتج",
                ],
                [
                    "name" => "delete",
                    "display_name" => "حذف منتج",
                ],
                [
                    "name" => "edit",
                    "display_name" => "تعديل منتج",
                ],
            ],
            "categories" => [
                [
                    "name" => "view",
                    "display_name" => "عرض الفئات",
                ],
                [
                    "name" => "create",
                    "display_name" => "إنشاء فئة",
                ],
                [
                    "name" => "delete",
                    "display_name" => "حذف فئة",
                ],
                [
                    "name" => "edit",
                    "display_name" => "تعديل فئة",
                ],
            ],

            "sub_categories" => [
                [
                    "name" => "view",
                    "display_name" => "عرض الفئات الفرعية",
                ],
                [
                    "name" => "create",
                    "display_name" => "إنشاء فئة فرعية",
                ],
                [
                    "name" => "delete",
                    "display_name" => "حذف فئة فرعية",
                ],
                [
                    "name" => "edit",
                    "display_name" => "تعديل فئة فرعية",
                ],
            ],


            "users" => [
                [
                    "name" => "view",
                    "display_name" => "عرض المستخدمين",
                    "display_section" => "المستخدمين",
                ],
                [
                    "name" => "create",
                    "display_name" => "إضافة مستخدم",
                    "display_section" => "المستخدمين",
                ],
                [
                    "name" => "edit",
                    "display_name" => "تعديل مستخدم",
                    "display_section" => "المستخدمين",
                ],
                [
                    "name" => "delete",
                    "display_name" => "حذف مستخدم",
                    "display_section" => "المستخدمين",
                ],
            ],

            "roles" => [
                [
                    "name" => "view",
                    "display_name" => "عرض الأدوار والصلاحيات",
                    "display_section" => "الأدوار والصلاحيات",
                ],
                [
                    "name" => "create",
                    "display_name" => "إضافة دور",
                    "display_section" => "الأدوار والصلاحيات",
                ],
                [
                    "name" => "edit",
                    "display_name" => "تعديل دور",
                    "display_section" => "الأدوار والصلاحيات",
                ],
                [
                    "name" => "delete",
                    "display_name" => "حذف دور",
                    "display_section" => "الأدوار والصلاحيات",
                ],
            ],
            'reports'=>[
                                [
                    "name" => "view",
                    "display_name" => "عرض التقارير",
                    "display_section" => "التقارير",
                ],
            ]

        ];
        // Create Roles
        $adminRole = Role::firstOrCreate([
            'name' => 'admin',
            'guard_name' => 'web',
        ]);

        $cashierRole = Role::firstOrCreate([
            'name' => 'cashier',
            'guard_name' => 'web',
        ]);

        // Create Permissions
        foreach ($permissions as $section => $items) {
            foreach ($items as $permission) {
                Permission::firstOrCreate(
                    [
                        'name' => $section . '.' . $permission['name'],
                        'guard_name' => 'web',
                    ],
                    [
                        'display_name' => $permission['display_name'],
                        'section' => $section,
                    ]
                );
            }
        }

        // Give All Permissions To Admin
                $adminRole->syncPermissions(
            Permission::where('guard_name', 'web')->get()
        );
        $users = [
            [
                "name" => "Admin",
                "email" => "admin@dar.net",
                "password" => Hash::make("p@ssw0rd"),
                'role' => 'admin'
            ],
            [
                "name" => "Cashier",
                "email" => "cashier@dar.net",
                "password" => Hash::make("p@ssw0rd"),
                'role' => 'cashier'
            ],
        ];
        foreach ($users as $data) {

            $user = User::updateOrCreate(
                ['email' => $data['email']],
                [
                    'name' => $data['name'],
                    'password' => $data['password'],
                ]
            );

            $user->assignRole($data['role']);
        }
    }
}
