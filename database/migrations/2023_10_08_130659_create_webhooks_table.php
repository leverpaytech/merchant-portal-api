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
        Schema::create('webhooks', function (Blueprint $table) {
            $table->id();
            $table->string('raw');
            $table->string('bank');
            $table->string('sessionId')->unique()->nullable();
            $table->string('bankSessionId')->nullable();
            $table->string('accountNumber')->nullable();
            $table->string('tranRemarks')->nullable();
            $table->decimal('amount', 15,3)->nullable()->default(0);
            $table->decimal('settledAmount', 15,3)->nullable()->default(0);
            $table->decimal('feeAmount', 15,3)->nullable()->default(0);
            $table->decimal('vatAmount', 15,3)->nullable()->default(0);
            $table->string('currency')->nullable();
            $table->string('transRef')->nullable();
            $table->string('settlementId')->unique()->nullable();
            $table->string('sourceAccountNumber')->nullable();
            $table->string('sourceAccountName')->nullable();
            $table->string('sourceBankName')->nullable();
            $table->string('channelId')->nullable();
            $table->string('tranDateTime')->nullable();
            $table->string('extra')->nullable();
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
        Schema::dropIfExists('webhooks');
    }
};
