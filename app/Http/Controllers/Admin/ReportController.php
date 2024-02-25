<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
class ReportController extends Controller
{
    public function getTodayTransactions(){
        $users = DB::table('users')->join('transactions', 'transactions);
    }
}
