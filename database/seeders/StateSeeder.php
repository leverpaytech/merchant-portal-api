<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class StateSeeder extends Seeder
{
    
    public function run()
    {
        $states = array(
            array('id' => '1','state_name' => 'Abia','country_id' => 1),
            array('id' => '2','state_name' => 'Adamawa','country_id' => 1),
            array('id' => '3','state_name' => 'Akwa Ibom','country_id' => 1),
            array('id' => '4','state_name' => 'Anambra','country_id' => 1),
            array('id' => '5','state_name' => 'Bauchi','country_id' => 1),
            array('id' => '6','state_name' => 'Bayelsa','country_id' => 1),
            array('id' => '7','state_name' => 'Benue','country_id' => 1),
            array('id' => '8','state_name' => 'Borno','country_id' => 1),
            array('id' => '9','state_name' => 'Cross River','country_id' => 1),
            array('id' => '10','state_name' => 'Delta','country_id' => 1),
            array('id' => '11','state_name' => 'Ebonyi','country_id' => 1),
            array('id' => '12','state_name' => 'Edo','country_id' => 1),
            array('id' => '13','state_name' => 'Ekiti','country_id' => 1),
            array('id' => '14','state_name' => 'Enugu','country_id' => 1),
            array('id' => '15','state_name' => 'FCT','country_id' => 1),
            array('id' => '16','state_name' => 'Gombe','country_id' => 1),
            array('id' => '17','state_name' => 'Imo','country_id' => 1),
            array('id' => '18','state_name' => 'Jigawa','country_id' => 1),
            array('id' => '19','state_name' => 'Kaduna','country_id' => 1),
            array('id' => '20','state_name' => 'Kano','country_id' => 1),
            array('id' => '21','state_name' => 'Katsina','country_id' => 1),
            array('id' => '22','state_name' => 'Kebbi','country_id' => 1),
            array('id' => '23','state_name' => 'Kogi','country_id' => 1),
            array('id' => '24','state_name' => 'Kwara','country_id' => 1),
            array('id' => '25','state_name' => 'Lagos','country_id' => 1),
            array('id' => '26','state_name' => 'Nasarawa','country_id' => 1),
            array('id' => '27','state_name' => 'Niger','country_id' => 1),
            array('id' => '28','state_name' => 'Ogun','country_id' => 1),
            array('id' => '29','state_name' => 'Ondo','country_id' => 1),
            array('id' => '30','state_name' => 'Osun','country_id' => 1),
            array('id' => '31','state_name' => 'Oyo','country_id' => 1),
            array('id' => '32','state_name' => 'Plateau','country_id' => 1),
            array('id' => '33','state_name' => 'Rivers','country_id' => 1),
            array('id' => '34','state_name' => 'Sokoto','country_id' => 1),
            array('id' => '35','state_name' => 'Taraba','country_id' => 1),
            array('id' => '36','state_name' => 'Yobe','country_id' => 1),
            array('id' => '37','state_name' => 'Zamfara','country_id' => 1)
         
        );
           
            foreach($states  as $key => $val)
            {
              DB::table('states')->insert([         
                  'state_name' => $val['state_name'],
                  'country_id' => $val['country_id']
              ]);
            }
        
        
          
    }
}
