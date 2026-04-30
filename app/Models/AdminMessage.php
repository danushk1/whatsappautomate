<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdminMessage extends Model
{
    protected $fillable = [
        'from_number',
        'user_id',
        'message',
        'is_read',
        'received_at',
        'expires_at',
    ];

    protected $casts = [
        'received_at' => 'datetime',
        'expires_at'  => 'datetime',
        'is_read'     => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
