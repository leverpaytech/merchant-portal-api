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
        Schema::create('kyc_verifications', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained();
            $table->string('phone', 13)->nullable();
            $table->string('email')->nullable();
            $table->string('nin', 11)->nullable();
            $table->string('bvn', 11)->nullable();
            $table->text('nin_details')->nullable();
            $table->text('bvn_details')->nullable();
            $table->text('contact_address')->nullable();
            $table->string('proof_of_address')->nullable();
            $table->string('live_face_verification')->nullable();
            $table->timestamp('phone_verified_at')->nullable();
            $table->timestamp('email_verified_at')->nullable();
            $table->timestamp('nin_verified_at')->nullable();
            $table->timestamp('bvn_verified_at')->nullable();
            $table->timestamp('address_verified_at')->nullable();
            $table->timestamp('live_face_verified_at')->nullable();
            $table->enum('status', ['pending', 'approved', 'declined'])->default('pending');
            $table->text('admin_comment')->nullable();
            $table->string('phone_verification_code')->nullable();
            $table->string('email_verification_code')->nullable();
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
        Schema::dropIfExists('kyc_verifications');
    }
};
