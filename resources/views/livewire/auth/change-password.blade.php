<div class="mx-auto max-w-md space-y-4">
    <h1 class="font-serif text-xl font-bold text-brand-navy">Change Password</h1>

    @if ($forced)
        <div class="rounded-md bg-amber-50 p-4 text-sm text-amber-800 ring-1 ring-amber-200">
            You're using a temporary password. Set your own password to continue.
        </div>
    @endif

    @if (session('status'))
        <div class="rounded-md bg-green-50 p-4 text-sm text-green-800 ring-1 ring-green-200">{{ session('status') }}</div>
    @endif

    <form wire:submit="save" class="space-y-4 rounded-xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
        <div>
            <label class="mb-1 block text-sm font-medium text-slate-700">Current Password</label>
            <input type="password" wire:model="current_password" autocomplete="current-password" class="w-full rounded-md border border-slate-300 px-3 py-2.5 text-base focus:border-brand-navy focus:ring-1 focus:ring-brand-navy">
            @error('current_password') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="mb-1 block text-sm font-medium text-slate-700">New Password</label>
            <input type="password" wire:model="new_password" autocomplete="new-password" class="w-full rounded-md border border-slate-300 px-3 py-2.5 text-base focus:border-brand-navy focus:ring-1 focus:ring-brand-navy">
            @error('new_password') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            <p class="mt-1 text-xs text-slate-500">At least 8 characters.</p>
        </div>

        <div>
            <label class="mb-1 block text-sm font-medium text-slate-700">Confirm New Password</label>
            <input type="password" wire:model="new_password_confirmation" autocomplete="new-password" class="w-full rounded-md border border-slate-300 px-3 py-2.5 text-base focus:border-brand-navy focus:ring-1 focus:ring-brand-navy">
        </div>

        <div class="flex justify-end gap-3 pt-2">
            @unless ($forced)
                <a href="{{ route('dashboard') }}" class="rounded-md border border-slate-300 px-4 py-2.5 text-sm text-slate-600 hover:bg-slate-50">Cancel</a>
            @endunless
            <button type="submit" class="rounded-md bg-brand-navy px-5 py-2.5 text-sm font-medium text-white hover:bg-brand-navy-light" wire:loading.attr="disabled">
                Change Password
            </button>
        </div>
    </form>
</div>
