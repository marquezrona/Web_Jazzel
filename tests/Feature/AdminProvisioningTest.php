<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminProvisioningTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_account_can_be_created_from_the_terminal(): void
    {
        $this->artisan('app:create-admin')
            ->expectsQuestion('Admin name', 'Owner')
            ->expectsQuestion('Admin email', 'owner@example.test')
            ->expectsQuestion('Password (minimum 12 characters)', 'A-very-strong-password!')
            ->expectsQuestion('Confirm password', 'A-very-strong-password!')
            ->expectsOutput('Administrator account created.')
            ->assertExitCode(0);

        $this->assertDatabaseHas('users', [
            'name' => 'Owner',
            'email' => 'owner@example.test',
            'role' => 'admin',
        ]);
    }
}
