<?php
namespace App\Helpers;

use Illuminate\Support\Facades\DB;
function logErrorsHelper($user_id, $message, $where, $encode = false)
{
    DB::table('error_loggers')->insert([
        'user_id' => $user_id,
        'message' => $encode?json_encode($message):$message,
        'where' => $where
    ]);
    return true;
}
