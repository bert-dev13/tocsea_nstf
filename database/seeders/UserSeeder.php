<?php
namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Create actual user account(s) for the application.
     */
    public function run(): void
    {
        User::firstOrCreate(
            ['email' => 'user@tocsea.com'],
            [
                'name' => 'TOCSEA User',
                'password' => Hash::make('user123'),
                'province' => 'Pangasinan',
                'municipality' => 'Burgos',
                'barangay' => 'San Miguel',
                'is_admin' => false,
                'role' => 'user',
                'is_disabled' => false,
            ]
        );

        User::updateOrCreate(
            ['email' => 'admin@tocsea.com'],
            [
                'name' => 'TOCSEA Admin',
                'password' => Hash::make('admin123'),
                'province' => null,
                'municipality' => null,
                'barangay' => null,
                'is_admin' => true,
                'role' => 'admin',
                'is_disabled' => false,
            ]
        );
    }
}
