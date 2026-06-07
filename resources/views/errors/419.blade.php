<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Session expired</title>
    <meta http-equiv="refresh" content="3;url={{ url('/login') }}">
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen flex items-center justify-center p-4" style="background:linear-gradient(135deg,#4f46e5,#7c3aed)">
    <div class="bg-white rounded-xl shadow-lg p-8 w-full max-w-md text-center">
        <div class="text-4xl mb-3">🔒</div>
        <h1 class="text-xl font-semibold mb-2">Session expired</h1>
        <p class="text-gray-500 text-sm mb-6">Your page was open too long, so for security we ended the session. Please log in again — redirecting you now…</p>
        <a href="{{ url('/login') }}" class="inline-block bg-indigo-600 text-white px-6 py-2.5 rounded-lg hover:bg-indigo-700">Log in again</a>
    </div>
</body>
</html>
