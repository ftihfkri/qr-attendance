@extends('layouts.app')
@section('title', 'Login')

@section('content')
<div class="min-h-screen flex items-center justify-center p-4" style="background:linear-gradient(135deg,#4f46e5,#7c3aed)">
    <div class="bg-white rounded-xl shadow-lg p-8 w-full max-w-md">
        <div class="text-center mb-6">
            <h1 class="text-2xl font-bold">Admin / Staff Login</h1>
            <p class="text-gray-500 text-sm mt-1">Sign in to manage attendance</p>
        </div>

        @if (session('status'))
            <div class="mb-4 text-sm text-green-700 bg-green-50 rounded p-3">{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="mb-4 text-sm text-red-700 bg-red-50 rounded p-3">{{ $errors->first() }}</div>
        @endif

        <form method="POST" action="/login" class="space-y-4">
            @csrf
            <div>
                <label class="block text-sm font-medium mb-1">Username</label>
                <input name="username" value="{{ old('username') }}" required autofocus
                    class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500">
            </div>
            <div>
                <label class="block text-sm font-medium mb-1">Password</label>
                <input type="password" name="password" required
                    class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500">
            </div>
            <button class="w-full bg-indigo-600 text-white py-3 rounded-lg hover:bg-indigo-700 font-medium">Log in</button>
        </form>

        <p class="text-center text-sm text-gray-500 mt-4">
            New here? <a href="/register" class="text-indigo-600 hover:underline">Register as user</a>
        </p>
    </div>
</div>
@endsection
