<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * Credentials are read from .env — never hardcode secrets here.
     */
    public function run(): void
    {
        // 1️⃣ System Admin
        User::updateOrCreate(
            ['email' => env('ADMIN_EMAIL', 'admin@nbis.com')],
            [
                'name'     => env('ADMIN_NAME', 'System Admin'),
                'phone'    => env('ADMIN_PHONE', '+201000000001'),
                'password' => Hash::make(env('ADMIN_PASSWORD', 'Admin@1234')),
                'role'     => 'admin',
            ]
        );

        // 2️⃣ Nurse — responsible for newborn registration & footprints
        User::updateOrCreate(
            ['email' => env('NURSE_EMAIL', 'nurse@nbis.com')],
            [
                'name'     => env('NURSE_NAME', 'Nurse Sarah'),
                'phone'    => env('NURSE_PHONE', '+201000000002'),
                'password' => Hash::make(env('NURSE_PASSWORD', 'Nurse@1234')),
                'role'     => 'nurse',
            ]
        );

        // 3️⃣ Police Officer — responsible for search & missing child reports
        User::updateOrCreate(
            ['email' => env('POLICE_EMAIL', 'police@nbis.com')],
            [
                'name'     => env('POLICE_NAME', 'Officer Ahmed'),
                'phone'    => env('POLICE_PHONE', '+201000000003'),
                'password' => Hash::make(env('POLICE_PASSWORD', 'Police@1234')),
                'role'     => 'police',
            ]
        );
    }
}