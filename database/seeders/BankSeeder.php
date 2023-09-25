<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class BankSeeder extends Seeder
{
    
    public function run()
    {
        $banks = array(
            array('id' => '1','name' =>'Access Bank Plc'),
            array('id' => '2','name' =>'Citibank Nigeria Limited'),
            array('id' => '3','name' =>'Ecobank Nigeria Plc'), 
            array('id' => '4','name' =>'Fidelity Bank Plc'), 
            array('id' => '5','name' =>'First Bank Nigeria Limited'),
            array('id' => '6','name' =>'First City Monument Bank Plc'), 
            array('id' => '7','name' =>'Globus Bank Limited'), 
            array('id' => '8','name' =>'Guaranty Trust Bank Plc'), 
            array('id' => '9','name' =>'Heritage Banking Company Ltd.'), 
            array('id' => '10','name' =>'Keystone Bank Limited'), 
            array('id' => '11','name' =>'Parallex Bank Ltd'), 
            array('id' => '12','name' =>'Polaris Bank Plc'), 
            array('id' => '13','name' =>'Premium Trust Bank'), 
            array('id' => '14','name' =>'Providus Bank'), 
            array('id' => '15','name' =>'Stanbic IBTC Bank Plc'), 
            array('id' => '16','name' =>'Standard Chartered Bank Nigeria Ltd.'), 
            array('id' => '17','name' =>'Sterling Bank Plc'), 
            array('id' => '18','name' =>'SunTrust Bank Nigeria Limited'), 
            array('id' => '19','name' =>'Titan Trust Bank Ltd'), 
            array('id' => '20','name' =>'Union Bank of Nigeria Plc'), 
            array('id' => '21','name' =>'United Bank For Africa Plc'), 
            array('id' => '22','name' =>'Unity Bank Plc'), 
            array('id' => '23','name' =>'Wema Bank Plc'), 
            array('id' => '24','name' =>'Zenith Bank Plc') 
        );


        foreach($banks  as $key => $val)
        {
            DB::table('banks')->insert([         
                'name' => $val['name']
            ]);
        }
    
 }
}
