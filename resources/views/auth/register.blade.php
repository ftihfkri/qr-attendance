@extends('layouts.app')
@section('title', 'Create account')

@section('content')
<div class="min-h-screen grid lg:grid-cols-2">
    <!-- Brand panel -->
    <div class="hidden lg:flex flex-col justify-between p-12 text-white relative overflow-hidden"
         style="background:linear-gradient(135deg,#4338ca 0%,#6d28d9 55%,#7c3aed 100%)">
        <div class="absolute inset-0 opacity-20" style="background-image:radial-gradient(circle at 20% 20%,#fff 0,transparent 40%),radial-gradient(circle at 80% 60%,#fff 0,transparent 35%)"></div>
        <div class="relative flex items-center gap-3">
            <div class="w-11 h-11 rounded-xl bg-white/15 backdrop-blur flex items-center justify-center">
                <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg>
            </div>
            <span class="font-semibold tracking-tight text-lg">Koperasi AGM</span>
        </div>
        <div class="relative">
            <h2 class="text-4xl font-extrabold leading-tight tracking-tight">Join the<br>attendance team.</h2>
            <p class="mt-4 text-white/80 max-w-sm">Create a staff account to help run check-ins and manage the membership roster.</p>
        </div>
        <div class="relative text-white/60 text-sm">© {{ date('Y') }} Koperasi AGM Attendance System</div>
    </div>

    <!-- Form panel -->
    <div class="flex items-center justify-center p-6 sm:p-12 bg-slate-50">
        <div class="w-full max-w-sm">
            <div class="flex lg:hidden items-center justify-center gap-2 mb-8">
                <div class="w-10 h-10 rounded-xl bg-brand-600 flex items-center justify-center text-white">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg>
                </div>
                <span class="font-semibold text-lg tracking-tight">Koperasi AGM</span>
            </div>

            <h1 class="text-2xl font-bold tracking-tight text-slate-900">Create your account</h1>
            <p class="text-slate-500 text-sm mt-1 mb-8">Register as a staff member.</p>

            @if ($errors->any())
                <div class="mb-4 text-sm text-red-700 bg-red-50 border border-red-100 rounded-lg p-3 flex items-start gap-2">
                    <svg class="w-4 h-4 mt-0.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m0 3.75h.008M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg>
                    <span>{{ $errors->first() }}</span>
                </div>
            @endif

            <form method="POST" action="/register" class="space-y-5">
                @csrf
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1.5">Username</label>
                    <input name="username" value="{{ old('username') }}" required autofocus autocomplete="username"
                        class="w-full px-3.5 py-2.5 border border-slate-300 rounded-lg text-slate-900 placeholder-slate-400 focus:border-brand-500 focus:ring-2 focus:ring-brand-500/30 outline-none transition"
                        placeholder="Choose a username">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1.5">Password</label>
                    <input type="password" name="password" required autocomplete="new-password"
                        class="w-full px-3.5 py-2.5 border border-slate-300 rounded-lg text-slate-900 placeholder-slate-400 focus:border-brand-500 focus:ring-2 focus:ring-brand-500/30 outline-none transition"
                        placeholder="At least 6 characters">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1.5">Confirm password</label>
                    <input type="password" name="password_confirmation" required autocomplete="new-password"
                        class="w-full px-3.5 py-2.5 border border-slate-300 rounded-lg text-slate-900 placeholder-slate-400 focus:border-brand-500 focus:ring-2 focus:ring-brand-500/30 outline-none transition"
                        placeholder="Re-enter your password">
                </div>
                <button class="w-full bg-brand-600 text-white py-2.5 rounded-lg hover:bg-brand-700 active:bg-brand-800 font-semibold shadow-sm transition focus:ring-2 focus:ring-brand-500/40 outline-none">
                    Create account
                </button>
            </form>

            <p class="text-center text-sm text-slate-500 mt-6">
                Already have an account? <a href="/login" class="text-brand-600 font-medium hover:text-brand-700 hover:underline">Sign in</a>
            </p>
        </div>
    </div>
</div>
@endsection
