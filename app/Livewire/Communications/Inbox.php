<?php

namespace App\Livewire\Communications;

use App\Models\CommunicationRecipient;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * communication_recipients is the source, never Laravel's notifications
 * table -- the notification is only ever the badge, per the V8A review.
 */
#[Layout('layouts.app')]
class Inbox extends Component
{
    public function render()
    {
        $recipients = CommunicationRecipient::where('resolved_user_id', Auth::id())
            ->with('communication')
            ->get()
            ->filter(fn (CommunicationRecipient $r) => $r->communication && ! $r->communication->isDraft())
            ->sortByDesc(fn (CommunicationRecipient $r) => $r->communication->published_at)
            ->values();

        return view('livewire.communications.inbox', [
            'recipients' => $recipients,
        ]);
    }
}
