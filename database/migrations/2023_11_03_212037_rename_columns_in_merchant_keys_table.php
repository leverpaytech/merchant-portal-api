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
        Schema::table('merchant_keys', function (Blueprint $table) {
            $table->renameColumn('test_secrete_key', 'test_secret_key');
            $table->renameColumn('live_secrete_key', 'live_secret_key');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('merchant_keys', function (Blueprint $table) {
            $table->renameColumn('test_secret_key', 'test_secrete_key');
            $table->renameColumn('live_secret_key', 'live_secrete_key');
        });
    }
};
