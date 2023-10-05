<?php

namespace App\Http\Controllers;

use App\Models\Investment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;

class InvestmentController extends Controller
{
    public function submitInvestment(Request $request){
        $data = $this->validate($request, [
            'first_name'=>'required|string',
            'last_name'=>'required|string',
            'middle_name'=>'nullable|string',
            'gender'=>'required|string',
            'dob'=>'required|string',
            'email'=>'required|string',
            'phone'=>'required|string',
            'country'=>'required|string',
            'state'=>'required|string',
            'amount'=>'required|numeric|min:1000',
            'password'=>['required', Password::min(8)->symbols()->uncompromised(), 'confirmed' ],
        ]);

        $data['password'] = bcrypt($data['password']);
        $invest = Investment::create($data);


    }
}
