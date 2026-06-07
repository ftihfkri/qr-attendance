<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Page not found</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen flex items-center justify-center p-4" style="background:linear-gradient(135deg,#4f46e5,#7c3aed)">
    <div class="bg-white rounded-xl shadow-lg p-8 w-full max-w-md text-center">
        <div class="text-6xl font-bold text-indigo-600 mb-2">404</div>
        <h1 class="text-xl font-semibold mb-2">Page not found</h1>
        <p class="text-gray-500 text-sm mb-6">The page you're looking for doesn't exist or may have moved.</p>
        <a href="{{ url('/') }}" class="inline-block bg-indigo-600 text-white px-6 py-2.5 rounded-lg hover:bg-indigo-700">Go to home</a>
    </div>
</body>
</html>
