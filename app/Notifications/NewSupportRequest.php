<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class NewSupportRequest extends Notification
{
    use Queueable;

    protected $supportRequest;
    protected $sender;

    public function __construct($supportRequest, $sender)
    {
        $this->supportRequest = $supportRequest;
        $this->sender = $sender;
    }

    public function via($notifiable)
    {
        return ['database'];
    }

    public function toArray($notifiable)
    {
        return [
            'support_request_id' => $this->supportRequest->id,
            'support_subject' => $this->supportRequest->subject,
            'sender_name' => $this->sender->name,
            'url' => route('support.show', $this->supportRequest),
            'type' => 'support_request',
        ];
    }
} 