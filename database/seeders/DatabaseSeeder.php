<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * @return void
     */
    public function run()
    {
        //$this->call(CountriesTableSeeder::class);
        //$this->call(StateSeeder::class);
        //$this->call(CitySeeder::class);
        DB::table('admin_logins')->insert([
            [
                'first_name' => 'LeverPay',
                'last_name' => 'Admin',
                'email' => 'development@leverpay.io',
                'password' => Hash::make('password@.2023'),
                'phone' => '0700000000',
                'gender' => 'Male'
            ]
        ]);

        DB::table('card_types')->insert([
            ['name'=>'Silver', 'limit'=>1000],
            ['name'=>'Gold', 'limit'=>2000],
            ['name'=>'Diamond', 'limit'=>3000],
            ['name'=>'Pink-Lady', 'limit'=>4000],
            ['name'=>'Enterprise', 'limit'=>5000],
        ]);
    }
}
