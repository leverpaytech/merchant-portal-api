<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * @return void
     */
    public function run()
    {
        // $this->call(CountriesTableSeeder::class);
        // $this->call(StateSeeder::class);
        // $this->call(CitySeeder::class);
        // $this->call(BankSeeder::class);
        // DB::table('admin_logins')->insert([
        //     [
        //         'first_name' => 'LeverPay',
        //         'last_name' => 'Admin',
        //         'email' => 'development@leverpay.io',
        //         'password' => Hash::make('password@.2023'),
        //         'phone' => '0700000000',
        //         'gender' => 'Male'
        //     ]
        // ]);

        // DB::table('card_types')->insert([
        //     ['name'=>'Silver', 'limit'=>1000],
        //     ['name'=>'Gold', 'limit'=>2000],
        //     ['name'=>'Diamond', 'limit'=>3000],
        //     ['name'=>'Pink-Lady', 'limit'=>4000],
        //     ['name'=>'Enterprise', 'limit'=>5000],
        // ]);

        // DB::table('currencies')->insert([
        //     ['name'=>'Dollar', 'currency_code'=>"$", 'status'=>1],
        //     ['name'=>'Naira', 'currency_code'=>"₦", 'status'=>1]
        // ]);

        // DB::table('exchange_rates')->insert([
        //     [
        //         'rate'=>900,
        //         'local_transaction_rate'=>1.5,
        //         'international_transaction_rate'=>1.9,
        //         'funding_rate'=>0,
        //         'conversion_rate'=>1,
        //         'created_at'=>now(),'updated_at'=>now()
        //     ],
        // ]);

        // DB::table('document_types')->insert([
        //     ['name'=>'Government Issued ID Card'],
        //     ['name'=>'International passport'],
        //     ['name'=>'Driver License'],
        //     ['name'=>'Voter\'s Card']
        // ]);

        // DB::table('lever_pay_account_no')->insert([
        //     ['bank'=>'Providus Bank', 'account_number'=> '1304212201', 'account_name'=>'Leverchain Technology Limited'],
        //     ['bank'=>'VFD MFB', 'account_number'=> '1029073449', 'account_name'=>'Leverchain Technology Limited'],
        // ]);

        // DB::table('vfd_discounts')->insert([
        //     ['uuid' => Str::uuid()->toString(), 'category'=>'Airtime', 'biller'=> '9MOBILE', 'biller_id'=>'9mobile', 'percent'=>'2'],
        //     ['uuid' => Str::uuid()->toString(), 'category'=>'Airtime', 'biller'=> 'AIRTEL', 'biller_id'=>'airng', 'percent'=>'1.5'],
        //     ['uuid' => Str::uuid()->toString(), 'category'=>'Airtime', 'biller'=> 'GLO', 'biller_id'=>'glong', 'percent'=>'2.2'],
        //     ['uuid' => Str::uuid()->toString(), 'category'=>'Airtime', 'biller'=> 'MTN', 'biller_id'=>'mtnng', 'percent'=>'1.5'],

        //     ['uuid' => Str::uuid()->toString(), 'category'=>'Data', 'biller'=> '9MOBILE', 'biller_id'=>'9mobile_data', 'percent'=>'2'],
        //     ['uuid' => Str::uuid()->toString(), 'category'=>'Data', 'biller'=> 'AIRTEL', 'biller_id'=>'airtel_data', 'percent'=>'1.5'],
        //     ['uuid' => Str::uuid()->toString(), 'category'=>'Data', 'biller'=> 'GLO', 'biller_id'=>'glo_data', 'percent'=>'2.2'],
        //     ['uuid' => Str::uuid()->toString(), 'category'=>'Data', 'biller'=> 'MTN', 'biller_id'=>'MTN_NIGERIA_DATA', 'percent'=>'1.5'],

        //     ['uuid' => Str::uuid()->toString(), 'category'=>'Utility', 'biller'=> 'Eko Postpaid', 'biller_id'=>'eko_electric_postpaid', 'percent'=>'0.5'],
        //     ['uuid' => Str::uuid()->toString(), 'category'=>'Utility', 'biller'=> 'Eko Prepaid', 'biller_id'=>'eko_electric_prepaid', 'percent'=>'0.5'],
        //     ['uuid' => Str::uuid()->toString(), 'category'=>'Utility', 'biller'=> 'Enugu Postpaid', 'biller_id'=>'enugu_electric_postpaid', 'percent'=>'0'],
        //     ['uuid' => Str::uuid()->toString(), 'category'=>'Utility', 'biller'=> 'Enugu Prepaid', 'biller_id'=>'enugu_electric_prepaid', 'percent'=>'0'],
        //     ['uuid' => Str::uuid()->toString(), 'category'=>'Utility', 'biller'=> 'Ibadan Prepaid', 'biller_id'=>'ibadan_electric_prepaid', 'percent'=>'0.2'],
        //     ['uuid' => Str::uuid()->toString(), 'category'=>'Utility', 'biller'=> 'Ibadan Postpaid', 'biller_id'=>'ibadan_electric_postpaid', 'percent'=>'0.2'],
        //     ['uuid' => Str::uuid()->toString(), 'category'=>'Utility', 'biller'=> 'Ikeja Postpaid', 'biller_id'=>'ikeja_electric_postpaid', 'percent'=>'0.5'],
        //     ['uuid' => Str::uuid()->toString(), 'category'=>'Utility', 'biller'=> 'Ikeja Prepaid', 'biller_id'=>'ikeja_electric_prepaid', 'percent'=>'0.5'],
        //     ['uuid' => Str::uuid()->toString(), 'category'=>'Utility', 'biller'=> 'Jos Postpaid', 'biller_id'=>'jos_electric_postpaid', 'percent'=>'0.2'],
        //     ['uuid' => Str::uuid()->toString(), 'category'=>'Utility', 'biller'=> 'Jos Prepaid', 'biller_id'=>'jos_electric_prepaid', 'percent'=>'0.4'],
        //     ['uuid' => Str::uuid()->toString(), 'category'=>'Utility', 'biller'=> 'Kaduna Prepaid', 'biller_id'=>'kaduna_electric_prepaid', 'percent'=>'0.4'],
        //     ['uuid' => Str::uuid()->toString(), 'category'=>'Utility', 'biller'=> 'Kano Prepaid', 'biller_id'=>'kedco_electric_prepaid', 'percent'=>'0.4'],
        //     ['uuid' => Str::uuid()->toString(), 'category'=>'Utility', 'biller'=> 'Port Harcourt Postpaid (Xpresspayments)', 'biller_id'=>'portharcourt_electric_postpaid', 'percent'=>'0.4'],
        //     ['uuid' => Str::uuid()->toString(), 'category'=>'Utility', 'biller'=> 'Port Harcourt Prepaid (Xpresspayments)', 'biller_id'=>'portharcourt_electric_postpaid', 'percent'=>'0.4'],
            
        //     ['uuid' => Str::uuid()->toString(), 'category'=>'Cable TV', 'biller'=> 'DSTV Subscription', 'biller_id'=>'dstv', 'percent'=>'0.7'],
        //     ['uuid' => Str::uuid()->toString(), 'category'=>'Cable TV', 'biller'=> 'GoTv', 'biller_id'=>'gotv', 'percent'=>'0.7'],
        //     ['uuid' => Str::uuid()->toString(), 'category'=>'Cable TV', 'biller'=> 'Showmax', 'biller_id'=>'SHOWMAX', 'percent'=>'1'],
        //     ['uuid' => Str::uuid()->toString(), 'category'=>'Cable TV', 'biller'=> 'Startimes', 'biller_id'=>'startimes', 'percent'=>'1'],
            
        //     ['uuid' => Str::uuid()->toString(), 'category'=>'Internet Subscription', 'biller'=> 'IPNX', 'biller_id'=>'IPNX', 'percent'=>'1.7'],
        // ]);
        //Quick teller cashback
        DB::table('quick_teller_discounts')->insert([
            ['uuid' => Str::uuid()->toString(), 'category'=>'Data', 'biller'=> 'Airtel Data Bundles_Prepaid', 'percent'=>'1.4'],
            ['uuid' => Str::uuid()->toString(), 'category'=>'Data', 'biller'=> 'Airtel Data Bundles Corporate/SME', 'percent'=>'1.4'],
            ['uuid' => Str::uuid()->toString(), 'category'=>'Data', 'biller'=> '9mobile Data_Bundles', 'percent'=>'3'],
            ['uuid' => Str::uuid()->toString(), 'category'=>'Data', 'biller'=> '9Mobile_Data_Bundles_VF', 'percent'=>'3'],
            ['uuid' => Str::uuid()->toString(), 'category'=>'Data', 'biller'=> 'GLO Data Bundle', 'percent'=>'2'],
            ['uuid' => Str::uuid()->toString(), 'category'=>'Data', 'biller'=> 'MTN HyNetflex Data_Plan', 'percent'=>'1.1'],
            ['uuid' => Str::uuid()->toString(), 'category'=>'Data', 'biller'=> 'MTN Mobile Data_Plan', 'percent'=>'1.1'],
            ['uuid' => Str::uuid()->toString(), 'category'=>'Airtime', 'biller'=> '9mobile Recharge (E-Top Up)', 'percent'=>'3'],
            ['uuid' => Str::uuid()->toString(), 'category'=>'Airtime', 'biller'=> 'Airtel Mobile Top-up (Prepaid)', 'percent'=>'1.4'],
            ['uuid' => Str::uuid()->toString(), 'category'=>'Airtime', 'biller'=> 'Airtel Top-up (Postpaid)', 'percent'=>'1.4'],
            ['uuid' => Str::uuid()->toString(), 'category'=>'Airtime', 'biller'=> 'Airtel Voice, Data &amp; SMS Bundles', 'percent'=>'1.4'],
            ['uuid' => Str::uuid()->toString(), 'category'=>'Airtime', 'biller'=> 'GLO QuickCharge (Top-up)', 'percent'=>'2'],
            ['uuid' => Str::uuid()->toString(), 'category'=>'Airtime', 'biller'=> 'MTN Direct Top-up (Postpaid)', 'percent'=>'1.1'],
            ['uuid' => Str::uuid()->toString(), 'category'=>'Airtime', 'biller'=> 'MTN Direct Top-up (Prepaid)', 'percent'=>'1.1'],
            //['uuid' => Str::uuid()->toString(), 'category'=>'Airtime', 'biller'=> 'GLO QuickCharge (Top-up)', 'percent'=>'2'],
        ]);
    }
}
