<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;

class PermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $permissions = [
            // User
            'View User',
            'Create User',
            'Edit User',
            'Delete User',
            'Restore User',
            'Force Delete User',

            // Department
            'View Department',
            'Create Department',
            'Edit Department',
            'Delete Department',

            // Product
            'View Product',
            'Create Product',
            'Edit Product',
            'Delete Product',

            // Supplier
            'View Supplier',
            'Create Supplier',
            'Edit Supplier',
            'Delete Supplier',

            // Role
            'View Role',
            'Create Role',
            'Edit Role',
            'Delete Role',

            // Category
            'View Category',
            'Create Category',
            'Edit Category',
            'Delete Category',

            // Inventory
            'View Inventory',
            'Create Inventory',
            'Edit Inventory',
            'Delete Inventory',
            'Restore Inventory',
            'Force Delete Inventory',

            // Loan
            'View Loan',
            'Create Loan',
            'Edit Loan',
            'Delete Loan',
            'Restore Loan',
            'Force Delete Loan',

            // Purchase
            'View Purchase',
            'Create Purchase',
            'Edit Purchase',
            'Delete Purchase',

            // Locate
            'View Locate',
            'Create Locate',
            'Edit Locate',
            'Delete Locate',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate([
                'name' => $permission,
                'guard_name' => 'web',
            ]);
        }
    }
}
