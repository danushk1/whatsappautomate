<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdminSetting extends Model
{
    protected $fillable = [
        'admin_whatsapp',
        'bank_name',
        'bank_account_no',
        'bank_account_name',
        'bank_branch',
    ];
}
