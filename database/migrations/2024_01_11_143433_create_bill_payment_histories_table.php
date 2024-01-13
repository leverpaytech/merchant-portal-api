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
        Schema::create('bill_payment_histories', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid');
            $table->foreignId('user_id')->constrained();
            $table->string('customerId')->comment('e.g Phone Number or Meter Token');
            $table->string('unit_purchased')->nullable();
            $table->decimal('price',13,5)->nullable();
            $table->decimal('amount',13,5);
            $table->string('category')->nullable();
            $table->string('biller')->nullable();
            $table->string('product')->nullable();
            $table->string('item')->nullable();
            $table->text('extra')->nullable();
            $table->text('provider_name')->nullable();
            $table->string('transaction_reference')->unique();
            $table->boolean('status')->default(true);
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
        Schema::dropIfExists('bill_payment_histories');
    }
};
