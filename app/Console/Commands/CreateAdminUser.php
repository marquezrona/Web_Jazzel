<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class CreateAdminUser extends Command
{
    protected $signature = 'app:create-admin';

    protected $description = 'Create an administrator account from the server terminal';

    public function handle(): int
    {
        $attributes = [
            'name' => trim((string) $this->ask('Admin name')),
            'email' => strtolower(trim((string) $this->ask('Admin email'))),
        ];
        $validator = Validator::make($attributes, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $password = $this->secret('Password (minimum 12 characters)');
        $confirmation = $this->secret('Confirm password');

        if (strlen((string) $password) < 12) {
            $this->error('Password must be at least 12 characters.');

            return self::FAILURE;
        }

        if (! hash_equals((string) $password, (string) $confirmation)) {
            $this->error('Password confirmation does not match.');

            return self::FAILURE;
        }

        User::query()->create([
            ...$attributes,
            'password' => Hash::make($password),
            'role' => 'admin',
        ]);

        $this->info('Administrator account created.');

        return self::SUCCESS;
    }
}
