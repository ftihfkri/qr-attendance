<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    public function index()
    {
        return view('admin.users');
    }

    public function list()
    {
        $users = User::orderBy('id')->get(['id', 'username', 'role', 'approved', 'created_at']);
        return response()->json(['status' => 'success', 'data' => $users]);
    }

    // Create a user or change an existing user's password (upsert by username).
    // Admin-created accounts are approved immediately.
    public function store(Request $request)
    {
        $data = $request->validate([
            'username' => ['required', 'string', 'min:3', 'max:50'],
            'password' => ['required', 'string', 'min:6'],
            'role'     => ['nullable', 'in:admin,staff'],
        ]);

        $user = User::updateOrCreate(
            ['username' => $data['username']],
            ['password' => Hash::make($data['password']), 'role' => $data['role'] ?? 'staff', 'approved' => true]
        );

        return response()->json(['status' => 'success', 'data' => ['id' => $user->id, 'username' => $user->username, 'role' => $user->role]]);
    }

    // Approve a pending (self-registered) account so it can log in.
    public function approve($id)
    {
        $user = User::find($id);
        if (!$user) {
            return response()->json(['status' => 'error', 'message' => 'User not found.'], 404);
        }
        $user->update(['approved' => true]);
        return response()->json(['status' => 'success', 'message' => "Approved '{$user->username}'."]);
    }

    public function destroy($id)
    {
        if (User::count() <= 1) {
            return response()->json(['status' => 'error', 'message' => 'Cannot delete the last user.'], 422);
        }
        $user = User::find($id);
        if (!$user) {
            return response()->json(['status' => 'error', 'message' => 'User not found.'], 404);
        }
        $user->delete();
        return response()->json(['status' => 'success']);
    }
}
