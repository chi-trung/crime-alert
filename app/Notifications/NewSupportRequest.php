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
            // Issue #192: both renderers — the notification page
            // (notifications/index.blade.php:29) and the /notifications/unread
            // dropdown JSON (NotificationController::unreadAjax) — read
            // data['message'] and fall back to 'Bạn có thông báo mới'. This
            // notification shipped without the key, so every admin's support
            // alert rendered as that generic line: who opened the thread and
            // about what — the only facts an admin triages on — were visible
            // nowhere until they clicked through. The sibling support
            // notification (NewSupportMessage:36) and all six post/comment/
            // like notifications already carry 'message'; the payload keys
            // below stay for anything reading them structurally.
            'message' => 'Người dùng '.$this->sender->name.' đã mở yêu cầu hỗ trợ: '.$this->supportRequest->subject,
            'support_request_id' => $this->supportRequest->id,
            'support_subject' => $this->supportRequest->subject,
            'sender_name' => $this->sender->name,
            'url' => route('support.show', $this->supportRequest),
            'type' => 'support_request',
        ];
    }
}
