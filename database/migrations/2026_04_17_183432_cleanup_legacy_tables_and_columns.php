<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Drop Legacy Voyager Tables
        $legacyTables = [
            'data_rows',
            'data_types',
            'menu_items',
            'menus',
            'permission_role',
            'permissions',
            'roles',
            'settings',
            'user_roles',
            'translations',
            'pages',
            'posts',
            'categories'
        ];

        foreach ($legacyTables as $table) {
            Schema::dropIfExists($table);
        }

        // 2. Remove Legacy Columns from Users
        Schema::table('users', function (Blueprint $table) {
            $columnsToDrop = ['role_id', 'role', 'avatar', 'settings'];

            foreach ($columnsToDrop as $column) {
                if (Schema::hasColumn('users', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // This is a destructive migration intended to permanently remove legacy architecture.
    }
};
