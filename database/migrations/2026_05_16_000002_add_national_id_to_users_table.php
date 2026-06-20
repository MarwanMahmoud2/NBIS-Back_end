<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** الرقم القومي لولي الأمر — للربط مع سجلات المواليد */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('national_id', 14)->nullable()->unique()->after('role');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('national_id');
        });
    }
};
