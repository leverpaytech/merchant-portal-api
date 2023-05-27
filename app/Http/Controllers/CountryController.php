<?php

namespace App\Http\Controllers;

use App\Models\Country;
use Illuminate\Http\Request;

class CountryController extends BaseController
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    /**
     * @OA\Get(
     ** path="/api/v1/get-countries",
     *   tags={"Countries, States and Cities"},
     *   summary="Get all countries",
     *   operationId="get country list",
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
    public function index()
    {
        $countries=Country::all();
        return $this->successfulResponse($countries, 'Countries successfully retrieved');
    }

    
}
