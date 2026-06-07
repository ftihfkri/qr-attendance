<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Koperasi Attendance')</title>
    <script src="https://cdn.tailwindcss.com"></script>
    @stack('head')
</head>
<body class="bg-gray-100 min-h-screen text-gray-800">
    @yield('content')

    <!-- Session expired popup -->
    <div id="sessionExpiredModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-[100] p-4">
        <div class="bg-white rounded-lg p-6 w-full max-w-sm text-center">
            <div class="text-3xl mb-2">🔒</div>
            <h3 class="text-lg font-semibold mb-1">Session expired</h3>
            <p class="text-sm text-gray-600 mb-4">You've been logged out due to inactivity. Please log in again to continue.</p>
            <button id="sessionLoginBtn" class="w-full bg-indigo-600 text-white py-2 rounded hover:bg-indigo-700">Log in again</button>
        </div>
    </div>

    <script>
        // Helper: fetch with CSRF + JSON, with session-expiry handling.
        window.csrfToken = document.querySelector('meta[name="csrf-token"]').content;

        function showSessionExpired() {
            const m = document.getElementById('sessionExpiredModal');
            if (m) { m.classList.remove('hidden'); m.classList.add('flex'); }
        }
        document.addEventListener('DOMContentLoaded', () => {
            const btn = document.getElementById('sessionLoginBtn');
            if (btn) btn.addEventListener('click', () => window.location.href = '/login');
        });

        window.apiFetch = async (url, opts = {}) => {
            const res = await fetch(url, {
                ...opts,
                headers: {
                    'X-CSRF-TOKEN': window.csrfToken,
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    ...(opts.body ? { 'Content-Type': 'application/json' } : {}),
                    ...(opts.headers || {}),
                },
            });
            // 401 = auth expired, 419 = CSRF/session token expired.
            if (res.status === 401 || res.status === 419) {
                showSessionExpired();
                return new Promise(() => {}); // halt callers; user must re-login
            }
            return res;
        };
    </script>
    @stack('scripts')
</body>
</html>
