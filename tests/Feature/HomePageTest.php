<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;

describe('Home page cases', function () {
    it("redirect to register", function () {
        visit('/')
            ->assertSee("Register")
            ->click('Register')
            ->assertPathIs('/register');
    });

});
