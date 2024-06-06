<?php

use App\Models\ExchangeRate;
use App\Services\WalletService;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Request;
use App\Models\User;
#use App\Http\Controllers\HomeController;
use App\Http\Controllers\KycController;
use App\Models\Account;
use App\Services\SmsService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Symfony\Component\Uid\Ulid;
use Webpatser\Uuid\Uuid;
use Carbon\Carbon;
use App\Services\ZeptomailService;

// Route::get('/', function (){
//     $html2 = "<h2 style='margin-bottom: 8px'>Details</h2>";
//     $to="ilelaboyealekan@gmail.com";
//     dd(preg_replace('/\n/', '', $html2));
//     ZeptomailService::sendMailZeptoMail("User Topup Request Notification", $html2, $to);
//     dd('done');
//     // $users=User::where('role_id', '1')->paginate(12, ['first_name']);
//     // dd($users);
//     // $file = public_path('bsmp.csv');
//     // $data=[];
//     // $header = null;
//     // if (($handle = fopen($file, 'r')) !== false)
//     // {
//     //     while (($row = fgetcsv($handle, 1000, ',')) !== false)
//     //     {
//     //         if (!$header)
//     //             $header = $row;
//     //         else
//     //             $data[] = array_combine($header, $row);
//     //     }
//     //     fclose($handle);
//     // }
//     // // dd($data);
//     // $unique = [];
//     // $double = [];
//     // $unknown = [];
//     // foreach ($data as $key => $value){
//     //     if(array_key_exists($value['DESTINATION ACCOUNT NUMBER'], $unique)){
//     //         $unique[$value['DESTINATION ACCOUNT NUMBER']]['count'] += 1;
//     //         array_push($unique[$value['DESTINATION ACCOUNT NUMBER']]['transactions'], $value);
//     //     }else{
//     //         $unique[$value['DESTINATION ACCOUNT NUMBER']]['transactions'] = [];
//     //         $unique[$value['DESTINATION ACCOUNT NUMBER']]['count'] = 1;
//     //         array_push($unique[$value['DESTINATION ACCOUNT NUMBER']]['transactions'], $value);

//     //         // array_push($unique, $value);
//     //     }
//     // }
//     // // dd($unique);

//     // foreach($unique as $key => $value){
//     //     $sup = DB::table('suppliers')->where('suppliers.account_number', $key)
//     //     ->join('company', 'suppliers.company_id', '=', 'company.id')
//     //     ->select('company.company_name', 'suppliers.name', 'suppliers.phone', 'suppliers.email', 'suppliers.account_name', 'suppliers.account_number')
//     //     ->get();

//     //     $unique[$key]['companyCount'] = count($sup);
//     //     if($unique[$key]['companyCount'] < 1){
//     //         $unknown[$key] = $value;
//     //     }
//     //     if($unique[$key]['companyCount'] > 1){
//     //         $double[$key] = $sup;
//     //         // array_push($double[$key],$sup);
//     //     }
//     //     $unique[$key]['company'] = $sup;
//     // }

//     // dd($unknown);
// });

// Route::get('/kycDetails', [KycController::class, 'getKycDocument'])->name('kycDetails');

// Route::get('upload', function(){
//     return view('upload');
// });

// Route::get('custom-clear-cache', function(){
//     \Artisan::call('route:cache');
//     \Artisan::call('config:cache');
//     \Artisan::call('cache:clear');
//     \Artisan::call('view:clear');
//     \Artisan::call('optimize:clear');
//     dd('done');
// });

// Route::post('/addKyc', [KycController::class, 'addKyc'])->name('addKyc');
