<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use RuntimeException;

class UserSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        if (app()->environment('production')) {
            $this->seedProductionAdmin();

            return;
        }

        $this->seedAdmin();
        $this->seedCustomer();
    }

    protected function seedProductionAdmin(): void
    {
        $email = trim((string) env('SEED_ADMIN_EMAIL', ''));
        $password = (string) env('SEED_ADMIN_PASSWORD', '');

        $validator = Validator::make([
            'email' => $email,
            'password' => $password,
        ], [
            'email' => ['required', 'email:rfc'],
            'password' => ['required', Password::min(16)->mixedCase()->numbers()->symbols()->uncompromised()],
        ]);

        if ($validator->fails()) {
            throw new RuntimeException(
                'UserSeeder refuses to create fixed demo credentials in production. Set SEED_ADMIN_EMAIL and a strong SEED_ADMIN_PASSWORD for one-time admin seeding.'
            );
        }

        User::updateOrCreate(
            ['email' => $email],
            [
                'name' => 'Production Admin',
                'first_name' => 'Production',
                'last_name' => 'Admin',
                'role' => User::ROLE_SUPER_ADMIN,
                'password' => $password,
            ]
        );
    }

    protected function seedAdmin(): void
    {
        User::updateOrCreate(
            ['email' => 'admin@ggwp.dev'],
            [
                'name' => 'Admin User',
                'first_name' => 'Admin',
                'last_name' => 'User',
                'role' => User::ROLE_SUPER_ADMIN,
                'password' => 'Admin123!Secure',
            ]
        );
    }

    protected function seedCustomer(): void
    {
        User::updateOrCreate(
            ['email' => 'customer@ggwp.dev'],
            [
                'name' => 'Demo Customer',
                'first_name' => 'Demo',
                'last_name' => 'Customer',
                'role' => 'customer',
                'password' => 'Customer123!Secure',
            ]
        );
    }
}
