<?php

namespace App\Models;

use App\Casts\EncryptedCast;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Notification extends Model
{
    use HasFactory;

    protected $table = 'notifications';
    protected $primaryKey = 'notification_id';
    
    // Disable default timestamps because notifications table uses custom notification_date and no updated_at
    public $timestamps = false;

    protected $fillable = [
        'contract_id',
        'user_id',
        'message',
        'notification_date',
        'is_read',
        'notification_type',
        'target_roles',
        'target_user_id',
    ];

    protected $casts = [
        'message'           => EncryptedCast::class,
        'notification_date' => 'datetime',
        'is_read'           => 'boolean',
    ];

    public function reads(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(NotificationRead::class, 'notification_id', 'notification_id');
    }
}
