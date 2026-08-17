<?php

namespace App\Livewire\Auth;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Authenticated password-change flow (P2B). Reachable at any time by any
 * logged-in user, and the ONLY normal-workflow page still reachable when
 * ForcePasswordChange has redirected here for must_change_password=true --
 * see routes/web.php for why this route sits outside that middleware's
 * group instead of being special-cased inside it.
 */
#[Layout('layouts.app')]
class ChangePassword extends Component
{
    public string $current_password = '';

    public string $new_password = '';

    public string $new_password_confirmation = '';

    public bool $forced = false;

    public function mount(): void
    {
        $this->forced = (bool) Auth::user()->must_change_password;
    }

    public function save(): void
    {
        $user = Auth::user();

        $validated = $this->validate([
            'current_password' => ['required', 'string'],
            'new_password' => ['required', 'string', 'confirmed', Password::min(8)],
        ]);

        if (! Hash::check($validated['current_password'], $user->password)) {
            $this->addError('current_password', 'The current password is incorrect.');

            return;
        }

        $user->update([
            'password' => $validated['new_password'],
            'must_change_password' => false,
        ]);

        $this->reset(['current_password', 'new_password', 'new_password_confirmation']);

        session()->flash('status', 'Password changed.');

        $this->redirect(route('dashboard'), navigate: true);
    }

    public function render()
    {
        return view('livewire.auth.change-password');
    }
}
