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
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained();
            $table->string('reference_no');
            $table->string('tnx_reference_no')->nullable();
            $table->decimal('amount', 13,5)->default(0);
            $table->text('transaction_details')->nullable();
            $table->decimal('balance',13, 5)->default(0);
            $table->integer('status')->default(0);
            $table->enum('type',['credit','debit'])->default('debit');
            $table->text('extra')->nullable();
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
        Schema::dropIfExists('transactions');
    }
};
