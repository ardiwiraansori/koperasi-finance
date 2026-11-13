<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

// Aman dijalankan berulang: php artisan db:seed --class=AdminSeeder
class AdminSeeder extends Seeder
{
    public function run(): void
    {
        if (! config('app.admin_password')) {
            $this->command?->warn('ADMIN_PASSWORD kosong di .env: TIDAK ada akun login yang dibuat. Isi, lalu jalankan: php artisan db:seed --class=AdminSeeder');
            return;
        }
        User::updateOrCreate(['email' => config('app.admin_email')], ['name' => 'Administrator', 'password' => config('app.admin_password'), 'role' => 'admin']);
    }
}
