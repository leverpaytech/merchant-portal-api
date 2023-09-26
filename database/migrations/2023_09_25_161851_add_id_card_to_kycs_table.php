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
        Schema::table('kycs', function (Blueprint $table) {
            $table->string('passport');
            $table->string('document_type_id')->constrained();
            $table->string('id_card_front');
            $table->string('id_card_back')->nullable();
            $table->string('country_id')->nullable()->constrained();
            $table->string('nin');
            $table->string('residential_address');
            $table->string('utility_bill')->nullable();
            $table->string('bvn');
            $table->string('business_address')->nullable();
            $table->dropColumn('document_name');
            $table->dropColumn('document_link');
            
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('kycs', function (Blueprint $table) {
            //
        });
    }
};
