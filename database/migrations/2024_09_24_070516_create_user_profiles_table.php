<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('user_profiles', function (Blueprint $table) {
            $table->id();
            $table->string('fname');
            $table->string('lname');
            $table->string('address')->nullable();
            $table->string('email')->unique();
            $table->string('post_code');
            $table->string('phone_no');
            $table->string('gender');
            $table->boolean('gdpr_sms_active')->default(false);
            $table->boolean('gdpr_email_active')->default(false); // Corrected typo here
            $table->string('referred_by')->nullable();
            $table->string('preferred_location')->nullable();
            $table->string('avatar')->nullable();
            $table->boolean('active')->default(false);
            $table->timestamps();


        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_profiles');
    }
};
