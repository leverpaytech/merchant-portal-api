<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RoleTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        DB::statement('SET FOREIGN_KEY_CHECKS = 0');
        DB::table("roles")->truncate();
        Role::insert([
            'id'              => 1,
            'name'            => 'admin',
        ]);

        Role::insert([
            'id'              => 2,
            'name'            => 'sub-admin',
        ]);

        Role::insert([
            'id'        => 3,
            'name'      => 'merchant',
        ]);
        Role::insert([
            'id'        => 4,
            'name'      => 'user',
        ]);
        Role::insert([
            'id'        => 5,
            'name'      => 'business',
        ]);
    }
}
