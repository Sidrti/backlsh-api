<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // Reset cached roles and permissions
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // create permissions
        $permissions = [
            'manage_billing',
            'manage_users_roles',
            'create_projects',
            'assign_tasks',
            'view_all_reports',
            'track_time',
            'create_tasks_in_assigned_projects',
            'view_own_reports'
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        // create roles and assign existing permissions

        // 1. Owner
        $ownerRole = Role::firstOrCreate(['name' => 'Owner']);
        $ownerRole->syncPermissions(Permission::all());

        // 2. Admin
        $adminRole = Role::firstOrCreate(['name' => 'Admin']);
        $adminRole->givePermissionTo([
            'create_projects',
            'assign_tasks',
            'view_all_reports',
            'track_time',
            'create_tasks_in_assigned_projects',
            'view_own_reports'
        ]);

        // 3. Member
        $memberRole = Role::firstOrCreate(['name' => 'Member']);
        $memberRole->givePermissionTo([
            'track_time',
            'create_tasks_in_assigned_projects',
            'view_own_reports'
        ]);

        // 4. Client
        $clientRole = Role::firstOrCreate(['name' => 'Client']);
        $clientRole->givePermissionTo([
            'create_tasks_in_assigned_projects',
            'view_own_reports'
        ]);
        
        // Ensure Clients cannot track time (by ommiting the track_time permission)
    }
}
