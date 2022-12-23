<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UsersTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $data = [
            'name' => 'Testimony',
            'email' => 'admin@leverpay.io',
            'password' => Hash::make('password'),
            'phone' => '07038635986',
            'role_id' => 1       
        ];
        \App\Models\User::create($data);
        
        $data = [
            'name' => 'Abdul',
            'email' => 'abdul@leverpay.io',
            'password' => Hash::make('password'),
            'phone' => '07038655985',
            'role_id' => 1       
        ];
        \App\Models\User::create($data);
    }
}
