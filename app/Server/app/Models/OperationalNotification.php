<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OperationalNotification extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'actor_user_id',
        'context_type',
        'context_id',
        'channel',
        'title',
        'body',
        'previous_status',
        'new_status',
        'payload',
        'read_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'read_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function actor()
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}
