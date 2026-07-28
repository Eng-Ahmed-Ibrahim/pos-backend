<?php

namespace Tests\Feature\Api\v1;

use Tests\TestCase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Foundation\Testing\RefreshDatabase;

class AuthTest extends TestCase
{
    use RefreshDatabase;    
    public function test_login_without_credentials()
    {
        $response = $this->postJson('/api/v1/login', []);

        $response->assertStatus(422);
    }
    public function test_login_with_invalid_credentials()
    {
        $response = $this->postJson('/api/v1/login', [
            'email' => 'test@gmail.com',
            'password' => 'invalidpassword',
        ]);
        $response->assertStatus(422)
        ->assertJsonValidationErrors(['email']);
        ;   
    }
    public function test_login_with_valid_email_with_invalid_password()
    {
        $user = \App\Models\User::factory()->create();
        $response = $this->postJson('/api/v1/login', [
            'email' => $user->email,
            'password' => 'invalidpassword',
        ]);
        $response->assertStatus(422)
        ->assertJsonValidationErrors(['email']);
    }
    public function test_login_with_valid_credentials()
    {
        $user = \App\Models\User::factory()->create([
            'password' => bcrypt('password'),
        ]);
        $response = $this->postJson('/api/v1/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);
        // ci/cd 111
        $response->assertStatus(200)
        ->assertJsonStructure([
            'token',
            'user',
        ]);
    }
    
}
