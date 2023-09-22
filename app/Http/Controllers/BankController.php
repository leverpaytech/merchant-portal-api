<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Bank;

class BankController extends BaseController
{
    /**
     * @OA\Get(
     ** path="/api/v1/user/get-all-banks}",
     *   tags={"User"},
     *   summary="Get all banks",
     *   operationId="get all banks",
     *
     *   @OA\Response(
     *      response=200,
     *       description="Success",
     *     ),
     *     security={
     *       {"bearer_token": {}}
     *     }
     *
     *)
     **/
    public function getBanks()
    {
        return $this->successfulResponse(Bank::where('status',1)->get(),'Bak List');
    }

}
