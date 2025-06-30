<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class NewCommentOnPost extends Notification
{
    use Queueable;

    protected $comment;
    protected $post;
    protected $postType;

    /**
     * Create a new notification instance.
     */
    public function __construct($comment, $post, $postType)
    {
        $this->comment = $comment;
        $this->post = $post;
        $this->postType = $postType; // 'alert' hoặc 'experience'
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->line('The introduction to the notification.')
            ->action('Notification Action', url('/'))
            ->line('Thank you for using our application!');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'comment_id' => $this->comment->id,
            'comment_content' => $this->comment->content,
            'comment_user' => $this->comment->user->name,
            'post_id' => $this->post->id,
            'post_title' => $this->post->title ?? $this->post->name,
            'post_type' => $this->postType,
            'url' => $this->postType === 'alert' ? route('alerts.show', $this->post->id) : route('experiences.show', $this->post->id),
        ];
    }
}
