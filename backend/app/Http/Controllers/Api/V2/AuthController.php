<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Helpers\Helpers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{

    // LOGIN
    public function login(Request $request)
    {
        $request->validate([
            'username' => 'required',
            'password' => 'required',
        ]);

        $user = User::where('username', $request->username)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
         
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
    }

    // LOGOUT
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Logged out successfully'
        ]);
    }

    // PROFILE
    public function user(Request $request)
    {
        $user = $request->user();

        $data = Cache::remember(
            "user_data_{$user->id}",
            3600,
            function () use ($user) {

                $user->load([
                    'roles.permissions'
                ]);

                return [
                    'user' => [
                        'id' => $user->id,
                        'name' => $user->name,
                        'username' => $user->username,
                    ],

                    'permissions' => $user->getAllPermissions()
                        ->pluck('name')
                        ->values()
                        ->toArray(),

                    'roles' => $user->getRoleNames()
                        ->values()
                        ->toArray(),

                    'settings' => Helpers::cache_settings()
                ];
            }
        );

        return response()->json($data);
    }
}
