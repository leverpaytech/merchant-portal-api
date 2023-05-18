<?php

namespace App\Http\Controllers;

use App\Models\UserLedger;
use Illuminate\Http\Request;

class UserLedgerController extends BaseController
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $userId=Auth::user()->id;
        $ledger=UserLedger::where('user_id', $userId)->get();

        return $this->successfulResponse($ledger, 'ledger details successfully retrieved');
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\UserLedger  $userLedger
     * @return \Illuminate\Http\Response
     */
    public function show(UserLedger $userLedger)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\UserLedger  $userLedger
     * @return \Illuminate\Http\Response
     */
    public function edit(UserLedger $userLedger)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\UserLedger  $userLedger
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, UserLedger $userLedger)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\UserLedger  $userLedger
     * @return \Illuminate\Http\Response
     */
    public function destroy(UserLedger $userLedger)
    {
        //
    }
}
