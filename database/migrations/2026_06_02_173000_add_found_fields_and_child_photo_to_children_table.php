<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('children', function (Blueprint $table) {
            $table->string('estimated_age')->nullable();
            $table->string('found_location')->nullable();
            $table->date('date_found')->nullable();
            $table->text('notes')->nullable();
            $table->string('child_photo_path')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('children', function (Blueprint $table) {
            $table->dropColumn(['estimated_age', 'found_location', 'date_found', 'notes', 'child_photo_path']);
        });
    }
};
