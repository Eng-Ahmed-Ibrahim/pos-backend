<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class UsersController extends Controller
{
    // GET USERS + ROLES
    public function index()
    {
        $users = User::with('roles')
            ->orderBy('id', 'desc')
            ->get();

        $roles = Role::orderBy('id', 'desc')->get();

        return response()->json([
            "status" => true,
            "data" => [
                "users" => $users,
                "roles" => $roles
            ]
        ]);
    }

    // CREATE USER
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|min:6',
            'role' => 'required|exists:roles,name',
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);

        // assign role
        $user->assignRole($request->role);

        return response()->json([
            "status" => true,
            "message" => "User created successfully",
            "data" => $user->load('roles')
        ]);
    }

    // UPDATE USER
    public function update(Request $request, $id)
    {
        $user = User::findOrFail($id);

        $request->validate([
            'name' => 'required|string',
            'email' => "required|email|unique:users,email,$id",
            'password' => 'nullable|min:6',
            'role' => 'required|exists:roles,name',
        ]);

        $user->update([
            'name' => $request->name,
            'email' => $request->email,
            'password' => $request->password
                ? Hash::make($request->password)
                : $user->password,
        ]);

        // sync role (replace old role)
        $user->syncRoles([$request->role]);

        return response()->json([
            "status" => true,
            "message" => "User updated successfully",
            "data" => $user->load('roles')
        ]);
    }

    // DELETE USER
    public function destroy($id)
    {
        $user = User::findOrFail($id);

        $user->delete();

        return response()->json([
            "status" => true,
            "message" => "User deleted successfully"
        ]);
    }
}