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
        Schema::create('missing_reports', function (Blueprint $table) {
            $table->id();
            
            // Child who is missing
            $table->foreignId('child_id')->constrained()->onDelete('cascade');
            
            // User who reported (parent or admin)
            $table->foreignId('reported_by')->constrained('users')->onDelete('cascade');
            
            // Report details
            $table->text('notes')->nullable();
            $table->string('last_seen_location')->nullable();
            $table->dateTime('last_seen_date')->nullable();
            $table->string('report_type')->default('missing'); // missing, suspicious, identity_issue
            
            // Report status
            $table->enum('status', ['active', 'resolved', 'closed'])->default('active');
            
            // Additional details
            $table->text('description')->nullable();
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('missing_reports');
    }
};
