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
        Schema::create('locations', function (Blueprint $table) {
            $table->id();
            $table->string('name')->trim()->unique()->required();
            $table->string('address')->trim()->required();
            $table->string('city')->trim()->required();
            $table->string('phone_number')->trim()->required();
            $table->string('post_code')->trim()->unique()->required(); // Changed postCode to post_code to follow Laravel convention
            $table->timestamps(); // Adds created_at and updated_at fields
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('locations');
    }
};
