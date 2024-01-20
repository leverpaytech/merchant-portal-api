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
        Schema::create('vfd_discounts', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid');
            $table->string('category')->comment('category name');
            $table->string('cat_id');
            $table->string('percent');
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
        Schema::dropIfExists('vfd_discounts');
    }
};
