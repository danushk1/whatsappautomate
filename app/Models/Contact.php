<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Contact extends Model
{
    protected $fillable = [
        'user_id',
        'phone',
        'wa_id',
        'name',
        'last_messaged_at',
        'is_blocked',
    ];

    protected $casts = [
        'last_messaged_at' => 'datetime',
        'is_blocked'       => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
