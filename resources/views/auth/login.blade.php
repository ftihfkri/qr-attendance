@extends('layouts.app')
@section('title', 'Sign in')

@section('content')
<div class="min-h-screen grid lg:grid-cols-2">
    <!-- Brand panel (hidden on small screens) -->
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
            <h2 class="text-4xl font-extrabold leading-tight tracking-tight">Attendance,<br>managed properly.</h2>
            <p class="mt-4 text-white/80 max-w-sm">QR check-in, a searchable membership roster, and live attendance status — all in one place.</p>
        </div>
        <div class="relative text-white/60 text-sm">© {{ date('Y') }} Koperasi AGM Attendance System</div>
    </div>

    <!-- Form panel -->
    <div class="flex items-center justify-center p-6 sm:p-12 bg-slate-50">
        <div class="w-full max-w-sm">
            <!-- Mobile brand -->
            <div class="flex lg:hidden items-center justify-center gap-2 mb-8">
                <div class="w-10 h-10 rounded-xl bg-brand-600 flex items-center justify-center text-white">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg>
                </div>
                <span class="font-semibold text-lg tracking-tight">Koperasi AGM</span>
            </div>

            <h1 class="text-2xl font-bold tracking-tight text-slate-900">Welcome back</h1>
            <p class="text-slate-500 text-sm mt-1 mb-8">Sign in to manage attendance.</p>

            @if (session('status'))
                <div class="mb-4 text-sm text-green-700 bg-green-50 border border-green-100 rounded-lg p-3">{{ session('status') }}</div>
            @endif
            @if ($errors->any())
                <div class="mb-4 text-sm text-red-700 bg-red-50 border border-red-100 rounded-lg p-3 flex items-start gap-2">
                    <svg class="w-4 h-4 mt-0.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m0 3.75h.008M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg>
                    <span>{{ $errors->first() }}</span>
                </div>
            @endif

            <form method="POST" action="/login" class="space-y-5">
                @csrf
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1.5">Username</label>
                    <input name="username" value="{{ old('username') }}" required autofocus autocomplete="username"
                        class="w-full px-3.5 py-2.5 border border-slate-300 rounded-lg text-slate-900 placeholder-slate-400 focus:border-brand-500 focus:ring-2 focus:ring-brand-500/30 outline-none transition"
                        placeholder="Enter your username">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1.5">Password</label>
                    <div class="relative">
                        <input id="password" type="password" name="password" required autocomplete="current-password"
                            class="w-full px-3.5 py-2.5 pr-11 border border-slate-300 rounded-lg text-slate-900 placeholder-slate-400 focus:border-brand-500 focus:ring-2 focus:ring-brand-500/30 outline-none transition"
                            placeholder="Enter your password">
                        <button type="button" id="togglePw" aria-label="Show password"
                            class="absolute inset-y-0 right-0 px-3 flex items-center text-slate-400 hover:text-slate-600">
                            <svg id="eyeIcon" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.7"><path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"/></svg>
                        </button>
                    </div>
                </div>
                <button class="w-full bg-brand-600 text-white py-2.5 rounded-lg hover:bg-brand-700 active:bg-brand-800 font-semibold shadow-sm transition focus:ring-2 focus:ring-brand-500/40 outline-none">
                    Sign in
                </button>
            </form>

            <p class="text-center text-sm text-slate-500 mt-6">
                New here? <a href="/register" class="text-brand-600 font-medium hover:text-brand-700 hover:underline">Create an account</a>
            </p>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    const pw = document.getElementById('password');
    const toggle = document.getElementById('togglePw');
    const eye = document.getElementById('eyeIcon');
    toggle.addEventListener('click', () => {
        const show = pw.type === 'password';
        pw.type = show ? 'text' : 'password';
        eye.innerHTML = show
            ? '<path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 0 0 1.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.451 10.451 0 0 1 12 4.5c4.756 0 8.773 3.162 10.065 7.498a10.522 10.522 0 0 1-4.293 5.774M6.228 6.228 3 3m3.228 3.228 3.65 3.65m7.894 7.894L21 21m-3.228-3.228-3.65-3.65m0 0a3 3 0 1 0-4.243-4.243m4.243 4.243L9.88 9.88"/>'
            : '<path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"/>';
    });
</script>
@endpush
