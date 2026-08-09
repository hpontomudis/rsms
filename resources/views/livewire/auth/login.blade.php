<div>
    <h2 class="mb-6 text-center font-serif text-lg font-bold text-brand-navy">Sign in</h2>

    <form wire:submit="submit" class="space-y-4">
        <div>
            <label for="email" class="mb-1 block text-sm font-medium text-slate-700">Email</label>
            <input
                type="email"
                id="email"
                wire:model="email"
                autofocus
                autocomplete="username"
                class="w-full rounded-md border border-slate-300 px-3 py-2.5 text-base focus:border-brand-navy focus:ring-brand-navy"
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
                class="w-full rounded-md border border-slate-300 px-3 py-2.5 text-base focus:border-brand-navy focus:ring-brand-navy"
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
            class="w-full rounded-md bg-brand-navy px-4 py-2.5 text-base font-medium text-white hover:bg-brand-navy-light"
            wire:loading.attr="disabled"
        >
            <span wire:loading.remove>Sign in</span>
            <span wire:loading>Signing in&hellip;</span>
        </button>
    </form>
</div>
