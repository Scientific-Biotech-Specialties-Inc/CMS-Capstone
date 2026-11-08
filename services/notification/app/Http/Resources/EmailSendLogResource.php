<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read int $id
 * @property-read int $notification_id
 * @property-read string $recipient_email
 * @property-read string $subject
 * @property-read string $status
 * @property-read ?string $error_message
 * @property-read ?\Illuminate\Support\Carbon $sent_at
 * @property-read \Illuminate\Support\Carbon $created_at
 */
final class EmailSendLogResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'notification_id' => $this->notification_id,
            'user_id' => $this->user_id,
            'recipient_email' => $this->recipient_email,
            'subject' => $this->subject,
            'status' => $this->status,
            'error_message' => $this->error_message,
            'sent_at' => $this->sent_at?->toISOString(),
            'created_at' => $this->created_at->toISOString(),
        ];
    }
}
