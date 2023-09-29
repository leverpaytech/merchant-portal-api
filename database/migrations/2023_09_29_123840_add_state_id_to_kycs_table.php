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
            $table->string('city_id')->nullable()->constrained();
            $table->string('place_of_birth')->nullable();
            $table->integer('card_type')->default(0);
            $table->string('document_type_id')->nullable()->constrained()->change();
            $table->string('passport')->nullable()->change();
            $table->string('id_card_front')->nullable()->change(); 
            $table->string('residential_address')->nullable()->change();
            $table->string('bvn')->nullable()->change();
            $table->string('nin')->nullable()->change();  
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
