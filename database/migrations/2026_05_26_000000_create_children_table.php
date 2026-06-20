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
        Schema::create('children', function (Blueprint $table) {
            $table->id();

            // Parent user (from infants table)
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('cascade');

            // Nurse who registered the child (from babies table)
            $table->foreignId('nurse_id')->nullable()->constrained('users')->onDelete('cascade');

            // Child information
            $table->string('name');
            $table->enum('gender', ['male', 'female'])->nullable();
            $table->date('birth_date')->nullable();

            // Parent information (from babies table)
            $table->string('mother_name')->nullable();
            $table->string('father_name')->nullable();
            $table->string('father_phone')->nullable();
            $table->string('father_national_id')->nullable();

            // Footprint/fingerprint image (merged from both tables)
            $table->string('footprint_path')->nullable();

            // NFC tag (from infants table)
            $table->string('nfc_tag_id')->nullable()->unique();

            // Status
            $table->string('status')->default('verified');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('children');
    }
};
