<?php

namespace App\Jobs;

use App\Helpers\Helper;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class SendNotificationEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $userId;
    public string $title;
    public string $message;
    public array $emailDetails;
    public ?string $actionUrl;
    public ?string $actionText;

    public function __construct(
        int $userId,
        string $title,
        string $message,
        array $emailDetails = [],
        ?string $actionUrl = null,
        ?string $actionText = null
    ) {
        $this->userId = $userId;
        $this->title = $title;
        $this->message = $message;
        $this->emailDetails = $emailDetails;
        $this->actionUrl = $actionUrl;
        $this->actionText = $actionText;
    }

    public function handle(): void
    {
        $recipient = User::find($this->userId);
        if (!$recipient) {
            return;
        }

        $data = [
            'title' => $this->title,
            'recipientName' => $recipient->name,
            'body' => $this->message,
            'details' => $this->emailDetails,
            'actionUrl' => $this->actionUrl,
            'actionText' => $this->actionText,
        ];

        $html = view('email.notification', $data)->render();
        Helper::sendEmail($recipient->email, $this->title, $html, $recipient->name);
    }
}
