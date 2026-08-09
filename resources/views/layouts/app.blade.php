<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="bg-slate-50 text-slate-900 antialiased">
    <div x-data="{ mobileNavOpen: false }" class="min-h-screen">
        <nav class="border-b border-slate-200 bg-white">
            <div class="mx-auto flex max-w-6xl items-center justify-between px-4 py-3">
                <a href="{{ route('dashboard') }}" class="flex flex-col leading-tight">
                    <span class="text-sm font-semibold text-slate-800">Rahai School</span>
                    <span class="text-xs text-slate-400">Management System</span>
                </a>

                <button
                    type="button"
                    class="rounded-md p-2 text-slate-500 hover:bg-slate-100 md:hidden"
                    @click="mobileNavOpen = !mobileNavOpen"
                    aria-label="Toggle navigation"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16" />
                    </svg>
                </button>

                <div class="hidden items-center gap-1 md:flex">
                    @include('layouts.partials.nav-links')
                </div>

                <div class="hidden items-center gap-3 md:flex">
                    <span class="text-sm text-slate-500">{{ auth()->user()->name }}</span>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="rounded-md border border-slate-300 px-3 py-1.5 text-sm text-slate-600 hover:bg-slate-50">
                            Log out
                        </button>
                    </form>
                </div>
            </div>

            <div x-show="mobileNavOpen" x-cloak class="border-t border-slate-200 px-4 pb-4 md:hidden">
                <div class="flex flex-col gap-1 pt-2">
                    @include('layouts.partials.nav-links')
                </div>
                <div class="mt-3 flex items-center justify-between border-t border-slate-100 pt-3">
                    <span class="text-sm text-slate-500">{{ auth()->user()->name }}</span>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="rounded-md border border-slate-300 px-3 py-1.5 text-sm text-slate-600 hover:bg-slate-50">
                            Log out
                        </button>
                    </form>
                </div>
            </div>
        </nav>

        <main class="mx-auto max-w-6xl px-4 py-6">
            @if (session('status'))
                <div class="mb-4 rounded-md bg-emerald-50 px-4 py-3 text-sm text-emerald-700 ring-1 ring-emerald-200">
                    {{ session('status') }}
                </div>
            @endif

            {{ $slot }}
        </main>
    </div>

    @livewireScripts
</body>
</html>
