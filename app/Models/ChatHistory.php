<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ChatHistory extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'phone',
        'role',
        'content',
        'tool_call_id',
        'tool_name',
        'timestamp'
    ];
}
