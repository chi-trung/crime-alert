<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;

class LikeCommentNotification extends Notification
{
    use Queueable;

    protected $liker;
    protected $comment;
    protected $post;
    protected $postType;

    public function __construct($liker, $comment, $post, $postType)
    {
        $this->liker = $liker;
        $this->comment = $comment;
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
            'comment_id' => $this->comment->id,
            'comment_content' => $this->comment->content,
            'post_id' => $this->post->id,
            'post_title' => $this->post->title ?? $this->post->name,
            'post_type' => $this->postType,
            'url' => $this->postType === 'alert' ? route('alerts.show', $this->post->id).'#comment-'.$this->comment->id : route('experiences.show', $this->post->id).'#comment-'.$this->comment->id,
            'message' => $this->liker->name . ' đã thả tim bình luận của bạn trong ' . $typeText . ': ' . ($this->post->title ?? $this->post->name),
        ];
    }
} 