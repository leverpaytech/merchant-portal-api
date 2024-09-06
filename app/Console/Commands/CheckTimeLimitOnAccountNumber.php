<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\{Account};
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use App\Services\ZeptomailService;

class CheckTimeLimitOnAccountNumber extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'accountnumber:timelimit';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'All dynamic account number are only valid for 1hour, check account numbers that has exceed 1hour after generation.';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        Account::where('created_at', '<', Carbon::now()->subMinutes(60))->where('bank', 'providus')->where('type', '!=', 'reserved')->update(['accountNumber' => DB::raw('CONCAT(id, "_",accountNumber)')]);
        // $a = Carbon::now();
        // $message ="<p>Hello Lekan,</p><p style='margin-bottom: 8px'>Testing CronJob</p><h4 style='margin-bottom: 8px'>{$a}</h4>";
        // $ubject="LeverPay CronJob Test";
        // $response=ZeptomailService::sendMailZeptoMail($ubject ,$message, "ilelaboyealekan@gmail.com");

        return Command::SUCCESS;
    }
}
