<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class LikePostNotification extends Notification
{
    use Queueable;

    protected $liker;
    protected $post;
    protected $postType;

    public function __construct($liker, $post, $postType)
    {
        $this->liker = $liker;
        $this->post = $post;
        $this->postType = $postType; // 'alert' hoặc 'experience'
    }

    public function via($notifiable)
    {
        return ['database'];
    }

    public function toArray($notifiable)
    {
        $typeText = $this->postType === 'alert' ? 'cảnh báo' : 'kinh nghiệm';
        return [
            'liker_id' => $this->liker->id,
            'liker_name' => $this->liker->name,
            'post_id' => $this->post->id,
            'post_title' => $this->post->title ?? $this->post->name,
            'post_type' => $this->postType,
            'url' => $this->postType === 'alert' ? route('alerts.show', $this->post->id) : route('experiences.show', $this->post->id),
            'message' => $this->liker->name . ' đã thả tim bài viết ' . $typeText . ': ' . ($this->post->title ?? $this->post->name),
        ];
    }
} 