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
    <div x-data="{ mobileNavOpen: false, userMenuOpen: false }" class="min-h-screen">

        {{-- Desktop sidebar --}}
        <aside class="fixed inset-y-0 left-0 z-30 hidden w-64 flex-col bg-brand-navy md:flex">
            <a href="{{ route('dashboard') }}" class="flex items-center gap-2.5 border-b border-white/10 px-4 py-4">
                <img src="{{ asset('images/logo.png') }}" alt="Rahai School" class="h-9 w-9">
                <span class="flex flex-col leading-tight">
                    <span class="font-serif text-sm font-bold text-white">Rahai School</span>
                    <span class="text-xs text-slate-400">Management System</span>
                </span>
            </a>

            <nav class="flex-1 overflow-y-auto px-3 py-4">
                @include('layouts.partials.sidebar-nav')
            </nav>
        </aside>

        {{-- Mobile sidebar drawer --}}
        <div x-show="mobileNavOpen" x-cloak class="fixed inset-0 z-40 md:hidden">
            <div class="fixed inset-0 bg-slate-900/50" @click="mobileNavOpen = false"></div>
            <aside class="relative flex h-full w-72 flex-col bg-brand-navy">
                <div class="flex items-center justify-between border-b border-white/10 px-4 py-4">
                    <a href="{{ route('dashboard') }}" class="flex items-center gap-2.5" @click="mobileNavOpen = false">
                        <img src="{{ asset('images/logo.png') }}" alt="Rahai School" class="h-9 w-9">
                        <span class="flex flex-col leading-tight">
                            <span class="font-serif text-sm font-bold text-white">Rahai School</span>
                            <span class="text-xs text-slate-400">Management System</span>
                        </span>
                    </a>
                    <button type="button" class="rounded-md p-1.5 text-slate-300 hover:bg-white/10 hover:text-white" @click="mobileNavOpen = false" aria-label="Close navigation">
                        <x-icon name="close" class="h-5 w-5" />
                    </button>
                </div>
                <nav class="flex-1 overflow-y-auto px-3 py-4">
                    @include('layouts.partials.sidebar-nav', ['closeOnNavigate' => true])
                </nav>
            </aside>
        </div>

        {{-- Main column --}}
        <div class="flex min-h-screen flex-col md:pl-64">
            <header class="sticky top-0 z-20 flex items-center justify-between border-b border-slate-200 bg-white px-4 py-3 md:px-6">
                <button
                    type="button"
                    class="rounded-md p-2 text-slate-500 hover:bg-slate-100 md:hidden"
                    @click="mobileNavOpen = true"
                    aria-label="Open navigation"
                >
                    <x-icon name="menu" class="h-6 w-6" />
                </button>

                <span class="hidden text-sm text-slate-400 md:inline">{{ now()->format('l, d F Y') }}</span>

                <div class="relative ml-auto" @click.outside="userMenuOpen = false">
                    <button type="button" class="flex items-center gap-2 rounded-md px-2 py-1.5 hover:bg-slate-50" @click="userMenuOpen = !userMenuOpen">
                        <span class="flex h-8 w-8 items-center justify-center rounded-full bg-brand-navy text-xs font-semibold text-white">
                            {{ collect(explode(' ', auth()->user()->name))->map(fn ($n) => mb_substr($n, 0, 1))->take(2)->implode('') }}
                        </span>
                        <span class="hidden text-left sm:block">
                            <span class="block text-sm font-medium text-slate-700">{{ auth()->user()->name }}</span>
                        </span>
                        <svg class="hidden h-4 w-4 text-slate-400 sm:block" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                        </svg>
                    </button>

                    <div x-show="userMenuOpen" x-cloak x-transition class="absolute right-0 z-30 mt-2 w-48 rounded-md bg-white py-1 shadow-lg ring-1 ring-slate-200">
                        <div class="border-b border-slate-100 px-4 py-2">
                            <p class="truncate text-sm font-medium text-slate-700">{{ auth()->user()->name }}</p>
                            <p class="truncate text-xs text-slate-400">{{ auth()->user()->email }}</p>
                        </div>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="flex w-full items-center gap-2 px-4 py-2 text-left text-sm text-slate-600 hover:bg-slate-50">
                                <x-icon name="logout" class="h-4 w-4" />
                                Log out
                            </button>
                        </form>
                    </div>
                </div>
            </header>

            <main class="mx-auto w-full max-w-6xl flex-1 px-4 py-6 md:px-6">
                @if (session('status'))
                    <div class="mb-4 rounded-md bg-emerald-50 px-4 py-3 text-sm text-emerald-700 ring-1 ring-emerald-200">
                        {{ session('status') }}
                    </div>
                @endif

                {{ $slot }}
            </main>
        </div>
    </div>

    @livewireScripts
</body>
</html>
