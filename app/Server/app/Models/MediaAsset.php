<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MediaAsset extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'context_type',
        'context_id',
        'disk',
        'path',
        'original_name',
        'mime_type',
        'extension',
        'size_bytes',
        'visibility',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
