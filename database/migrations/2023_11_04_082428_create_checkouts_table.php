<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('checkouts', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid');
            $table->foreignId('merchant_id');
            $table->double('amount', 8,2)->default(0);
            $table->double('vat', 8,2)->default(0);
            $table->double('fee', 8,2)->default(0);
            $table->double('total', 8,2)->default(0);
            $table->string('product')->nullable();
            $table->string('email')->nullable();
            $table->string('access_code')->unique();
            $table->string('authorization_url');
            $table->string('merchant_reference')->nullable();
            $table->integer('status')->default(0);
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('phone')->nullable();
            $table->string('currency')->default('naira');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('checkouts');
    }
};
