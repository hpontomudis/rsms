<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-brand-navy text-slate-900 antialiased">
    <div class="flex min-h-screen flex-col items-center justify-center px-4 py-12">
        <div class="mb-8 flex flex-col items-center text-center">
            <img src="{{ asset('images/logo.png') }}" alt="Rahai School" class="mb-4 h-24 w-24">
            <h1 class="font-serif text-2xl font-bold text-white">Rahai School</h1>
            <p class="text-sm text-brand-grey">School Management System</p>
        </div>

        <div class="w-full max-w-sm rounded-xl bg-white p-6 shadow-lg">
            {{ $slot }}
        </div>
    </div>
</body>
</html>
