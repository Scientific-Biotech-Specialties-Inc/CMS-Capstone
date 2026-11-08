<?php

declare(strict_types=1);

namespace App\Models;

use App\Casts\EncryptedCast;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

final class EmailSendLog extends Model
{
    use HasFactory;

    protected $table = 'email_send_logs';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'notification_id',
        'user_id',
        'recipient_email',
        'subject',
        'status',
        'error_message',
        'sent_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'notification_id' => 'integer',
        'user_id' => 'integer',
        'recipient_email' => EncryptedCast::class,
        'sent_at' => 'datetime',
        'created_at' => 'datetime',
    ];

    /**
     * Disable the updated_at timestamp.
     */
    public const UPDATED_AT = null;

    /**
     * Get the notification associated with the log.
     */
    public function notification(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Notification::class, 'notification_id', 'notification_id');
    }
}
