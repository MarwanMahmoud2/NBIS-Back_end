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
        Schema::create('verification_logs', function (Blueprint $table) {
            $table->id();

            // ربط السجل بجدول الأطفال - عند حذف الطفل يُحذف السجل تلقائياً
            $table->foreignId('child_id')->constrained('children')->onDelete('cascade');

            // ربط السجل بالمستخدم (الضابط/الأدمن) - يقبل null إذا كان التسجيل تلقائي أو بدون Auth
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('set null');

            $table->string('type'); // Found Child أو Missing Child
            $table->string('status')->default('pending'); // pending, verified, matched
            $table->string('child_name')->nullable();
            $table->string('verified_by')->nullable();
            $table->date('date')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('verification_logs');
    }
};