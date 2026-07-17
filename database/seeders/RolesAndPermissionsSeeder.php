<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permissions = [
            'dashboard.view',
            'customers.view',
            'customers.manage',
            'users.view',
            'users.manage',
            'plans.view',
            'plans.manage',
            'subscriptions.view',
            'subscriptions.manage',
            'documents.generate',
            'documents.review',
            'audit.view',
            'settings.manage',
        ];

        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        Role::findOrCreate('super-admin', 'web')->syncPermissions($permissions);

        Role::findOrCreate('admin-cliente', 'web')->syncPermissions([
            'dashboard.view',
            'users.view',
            'users.manage',
            'subscriptions.view',
            'documents.generate',
            'documents.review',
            'audit.view',
        ]);

        Role::findOrCreate('assessor', 'web')->syncPermissions([
            'dashboard.view',
            'documents.generate',
            'documents.review',
        ]);

        Role::findOrCreate('consulta', 'web')->syncPermissions([
            'dashboard.view',
            'customers.view',
            'subscriptions.view',
        ]);
    }
}
