<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class NewSupportMessage extends Notification
{
    use Queueable;

    protected $supportRequest;
    protected $message;
    protected $sender;

    public function __construct($supportRequest, $message, $sender)
    {
        $this->supportRequest = $supportRequest;
        $this->message = $message;
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
            'message' => $this->message->message,
            'url' => route('support.show', $this->supportRequest),
            'type' => 'support',
        ];
    }
} 