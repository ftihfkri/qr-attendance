<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Koperasi Attendance')</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: { sans: ['Inter', 'ui-sans-serif', 'system-ui', 'sans-serif'] },
                    colors: {
                        brand: {
                            50: '#eef2ff', 100: '#e0e7ff', 200: '#c7d2fe', 300: '#a5b4fc',
                            400: '#818cf8', 500: '#6366f1', 600: '#4f46e5', 700: '#4338ca',
                            800: '#3730a3', 900: '#312e81',
                        },
                    },
                    boxShadow: { card: '0 1px 3px rgba(15,23,42,.08), 0 10px 30px -12px rgba(15,23,42,.18)' },
                },
            },
        };
    </script>
    <style>
        body { font-family: 'Inter', ui-sans-serif, system-ui, sans-serif; -webkit-font-smoothing: antialiased; }
    </style>
    @stack('head')
</head>
<body class="bg-slate-50 min-h-screen text-slate-800">
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
