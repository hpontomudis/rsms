<?php

namespace App\Livewire\Communications;

use App\Models\Communication;
use App\Services\CommunicationService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Create extends Component
{
    public string $display_sender = 'Rahai School';

    public string $title = '';

    public string $body = '';

    public string $priority = 'normal';

    public string $expires_at = '';

    public function mount(): void
    {
        $this->authorize('create', Communication::class);
    }

    public function save(CommunicationService $communications)
    {
        $this->authorize('create', Communication::class);

        $validated = $this->validate([
            'display_sender' => ['required', 'string', 'max:255'],
            'title' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:5000'],
            'priority' => ['required', 'in:normal,important,urgent'],
            'expires_at' => ['nullable', 'date'],
        ]);

        $communication = $communications->createDraft(Auth::user(), [
            'display_sender' => $validated['display_sender'],
            'title' => $validated['title'],
            'body' => $validated['body'],
            'priority' => $validated['priority'],
            'expires_at' => $validated['expires_at'] !== '' ? $validated['expires_at'] : null,
        ]);

        return $this->redirect(route('communications.show', $communication), navigate: true);
    }

    public function render()
    {
        return view('livewire.communications.create');
    }
}
