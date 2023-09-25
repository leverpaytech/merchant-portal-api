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
        Schema::table('exchange_rates', function (Blueprint $table) {
            $table->decimal('rate',13,5)->default(0)->change();
            $table->integer('status')->change();
            $table->decimal('local_transaction_rate',13,5)->default(0);
            $table->decimal('international_transaction_rate',13,5)->default(0);
            $table->decimal('funding_rate',13,5)->default(0);
            $table->decimal('conversion_rate',13,5)->default(0);
            $table->text('notes')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        //
    }
};
