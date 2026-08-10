<?php

namespace Tests\Feature\Api\Auth\v1;

use App\Models\User;
use Illuminate\Support\Facades\Hash;

test("without credition", function () {
    $response = $this->postJson('/api/v1/login', []);
    $response->assertStatus(422);
});
test("with invalid password and valid email", function () {
    $user = User::factory()->create(["password" => Hash::make("12345678")]);
    $respone = $this->postJson('/api/v1/login', [
        "email" => $user->email,
        "password" => "afasf2"
    ]);
    $respone->assertStatus(422);
});
test("with valid password and invalid email", function () {
    $user = User::factory()->create([
        "password" => Hash::make("12345678")
    ]);

    $response = $this->postJson('/api/v1/login', [
        "email" => "asfasfas@gmail.com",
        "password" => "12345678"
    ]);

    $response->assertStatus(422);
});
test("login avaliable only for guest authenticated user not able to login",function(){
    $user = User::factory()->create(["password" => Hash::make("12345678")]);
    $response = $this->actingAs($user,'sanctum')->postJson('/api/v1/login',[
        "password"=>"12345678",
        "email"=>$user->email
    ]);
    $response->assertStatus(302);
});
test("Can User Login with Correct", function () {
    $user = User::factory()->create(["password" => Hash::make("12345678")]);
    $response = $this->postJson('/api/v1/login', [
        'email' => $user->email,
        'password' => '12345678',
    ]);
    $response->assertJsonStructure(['token', 'user']);
    $response->assertStatus(200);
});

test("rate limited", function () {
    $user = User::factory()->create(["password" => Hash::make("12345678")]);
    for ($i = 0; $i < 60; $i++) {
        $response = $this->postJson('/api/v1/login', [
            'email' => $user->email,
            'password' => 'sdgfwetwef',
        ]);
    }
        $response = $this->postJson('/api/v1/login', [
        'email' => $user->email,
        'password' => '12345678',
    ]);
    $response->assertStatus(429);
});
