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
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('name')->trim()->required();
            $table->string('brand')->nullable()->trim();
            $table->text('description')->nullable()->trim();
            $table->decimal('price', 8, 2)->required(); // Assuming price is a decimal with 2 decimal places
            $table->string('image')->nullable();
            $table->enum('type', ['Product', 'Service'])->default('Product'); // Enum for type field
            $table->timestamps(); // Adds created_at and updated_at fields
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
