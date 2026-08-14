<?php

namespace App\Notifications;

use App\Models\Communication;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * The badge/alert only -- "you have a new Communication." Minimal payload,
 * routing data only; Communication + CommunicationRecipient remain the
 * canonical content and history. Deleting this notification never touches
 * either (V8A architecture review item 20).
 */
class NewCommunicationPublished extends Notification
{
    use Queueable;

    public function __construct(private readonly Communication $communication) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'communication_id' => $this->communication->id,
            'title' => $this->communication->title,
            'display_sender' => $this->communication->display_sender,
            'priority' => $this->communication->priority,
        ];
    }
}
