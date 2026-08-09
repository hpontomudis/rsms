<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-slate-100 text-slate-900 antialiased">
    <div class="flex min-h-screen flex-col items-center justify-center px-4 py-12">
        <div class="mb-8 text-center">
            <h1 class="text-xl font-semibold text-slate-800">Rahai School</h1>
            <p class="text-sm text-slate-500">School Management System</p>
        </div>

        <div class="w-full max-w-sm rounded-xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
            {{ $slot }}
        </div>
    </div>
</body>
</html>
