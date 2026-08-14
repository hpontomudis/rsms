<?php

namespace App\Livewire\Communications;

use App\Models\Communication;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Draft / Published / Archived, scoped exactly like CommunicationPolicy::view():
 * a non-teacher holder of communications.view/manage sees everything; a
 * teacher (or anyone without that broader permission) sees only what they
 * authored, plus anything they are a materialized recipient of.
 */
#[Layout('layouts.app')]
class Index extends Component
{
    public string $tab = 'draft';

    public function mount(): void
    {
        $this->authorize('viewAny', Communication::class);
    }

    public function render()
    {
        $user = Auth::user();
        $seesEverything = ($user->can('communications.view') || $user->can('communications.manage')) && ! $user->hasRole('teacher');

        $query = Communication::query()->where('status', $this->tab);

        if (! $seesEverything) {
            $query->where(function ($q) use ($user) {
                $q->where('created_by_user_id', $user->id)
                    ->orWhereHas('recipients', fn ($r) => $r->where('resolved_user_id', $user->id));
            });
        }

        return view('livewire.communications.index', [
            'communications' => $query->with('createdBy')->orderByDesc('id')->get(),
            'canCreate' => $user->can('create', Communication::class),
        ]);
    }
}
