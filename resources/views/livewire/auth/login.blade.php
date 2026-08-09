<div>
    <h2 class="mb-6 text-center text-lg font-semibold text-slate-800">Sign in</h2>

    <form wire:submit="submit" class="space-y-4">
        <div>
            <label for="email" class="mb-1 block text-sm font-medium text-slate-700">Email</label>
            <input
                type="email"
                id="email"
                wire:model="email"
                autofocus
                autocomplete="username"
                class="w-full rounded-md border border-slate-300 px-3 py-2.5 text-base focus:border-slate-500 focus:ring-slate-500"
            >
            @error('email')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="password" class="mb-1 block text-sm font-medium text-slate-700">Password</label>
            <input
                type="password"
                id="password"
                wire:model="password"
                autocomplete="current-password"
                class="w-full rounded-md border border-slate-300 px-3 py-2.5 text-base focus:border-slate-500 focus:ring-slate-500"
            >
            @error('password')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <label class="flex items-center gap-2 text-sm text-slate-600">
            <input type="checkbox" wire:model="remember" class="rounded border-slate-300">
            Remember me
        </label>

        <button
            type="submit"
            class="w-full rounded-md bg-slate-900 px-4 py-2.5 text-base font-medium text-white hover:bg-slate-800"
            wire:loading.attr="disabled"
        >
            <span wire:loading.remove>Sign in</span>
            <span wire:loading>Signing in&hellip;</span>
        </button>
    </form>
</div>
