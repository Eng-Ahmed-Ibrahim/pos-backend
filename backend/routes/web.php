<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\Models\Role;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::get('/', function () {
    $user = User::where('username', "admin")->first();

    if (!$user || !Hash::check("p@ssw0rd@admin.Nozom1", $user->password)) {
     
              return response()->json([
        "message"=>"Invalid credentials",
        "status"=>false
    ],422);

    }


    $token = $user->createToken('auth_token')->plainTextToken;

    return response()->json([
        'user' => $user,
        'token' => $token,
        'permissions' => $user->getAllPermissions()->pluck('name'),
        'roles' => $user->getRoleNames(),
    ]);
});
