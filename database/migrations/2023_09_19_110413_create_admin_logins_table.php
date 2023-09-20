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
        Schema::create('admin_logins', function (Blueprint $table) {
            $table->id();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('address')->nullable();;
            $table->string('phone')->nullable();
            $table->enum('gender',['','male','female'])->nullable();
            $table->string('email')->unique();
            $table->string('password');
            $table->string('passport')->nullable();
            $table->rememberToken();
            $table->boolean('status')->default(true);
            $table->boolean('verify_email_status')->default(false);
            $table->string('forgot_password_token')->nullable();
            $table->timestamp('last_seen_at')->nullable();
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
        Schema::dropIfExists('admin_logins');
    }
};
