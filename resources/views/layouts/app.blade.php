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
    <script>
        // Helper: fetch with CSRF + JSON
        window.csrfToken = document.querySelector('meta[name="csrf-token"]').content;
        window.apiFetch = (url, opts = {}) => fetch(url, {
            ...opts,
            headers: {
                'X-CSRF-TOKEN': window.csrfToken,
                'Accept': 'application/json',
                ...(opts.body ? { 'Content-Type': 'application/json' } : {}),
                ...(opts.headers || {}),
            },
        });
    </script>
    @stack('scripts')
</body>
</html>
