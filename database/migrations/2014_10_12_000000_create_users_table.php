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
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('address')->nullable();;
            $table->string('phone')->nullable();
            $table->enum('gender',['','male','female'])->nullable();
            $table->string('dob')->nullable();
            $table->string('email')->unique();
            $table->string('password');
            $table->string('picture')->nullable();
            $table->string('state')->nullable();
            $table->string('city')->nullable();
            $table->string('zip_code')->nullable();
            $table->rememberToken();
            $table->boolean('status')->default(true);
            $table->boolean('verify_email_status')->default(false);
            $table->timestamp('last_seen_at')->nullable();
            $table->enum('role_id',[0,1])->nullable();
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
        Schema::dropIfExists('users');
    }
};
