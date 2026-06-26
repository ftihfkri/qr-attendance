<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function showLogin()
    {
        if (Auth::check()) {
            return redirect('/admin');
        }
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'username' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        if (Auth::attempt($credentials, $request->boolean('remember'))) {
            // Self-registered accounts stay locked out until an admin approves them.
            if (!Auth::user()->approved) {
                Auth::logout();
                return back()->withErrors(['username' => 'Your account is awaiting admin approval.'])->onlyInput('username');
            }
            $request->session()->regenerate();
            return redirect()->intended('/admin');
        }

        return back()->withErrors(['username' => 'Invalid username or password.'])->onlyInput('username');
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect('/login');
    }

    public function showRegister()
    {
        return view('auth.register');
    }

    // Public self-registration -> creates a PENDING 'staff' account that cannot
    // log in until an admin approves it on the Users page.
    public function register(Request $request)
    {
        $data = $request->validate([
            'username' => ['required', 'string', 'min:3', 'max:50', 'unique:users,username'],
            'password' => ['required', 'string', 'min:6', 'confirmed'],
        ]);

        User::create([
            'username' => $data['username'],
            'password' => Hash::make($data['password']),
            'role'     => 'staff',
            'approved' => false,
        ]);

        return redirect('/login')->with('status', 'Account requested. An admin must approve it before you can log in.');
    }
}
