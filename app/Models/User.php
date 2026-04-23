<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Support\Str;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;

class User extends Authenticatable implements FilamentUser
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'api_key',
        'is_admin',
        'status',
        'address',
        'whatsapp_phone_number_id',
        'whatsapp_business_account_id',
        'whatsapp_access_token',
        'whatsapp_number',
        'target_mode',
        'target_value',
        'target_api_key',
        'company_details',
        'credits',
        'session_id',
        'is_active',
        'is_autoreply_enabled',
        'autoreply_message',
        'autoreply_credits',
        'has_claimed_autoreply_bonus',
        'inventory_api_url',
        'google_sheet_name',
        'order_api_url',
        'google_id',
        'balance',
        // New Hybrid WhatsApp fields
        'connection_type',
        'whatsapp_session',
        'whatsapp_qr_code_path',
        'whatsapp_connected_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'is_admin' => 'boolean',
        'is_active' => 'boolean',
        'is_autoreply_enabled' => 'boolean',
        'has_claimed_autoreply_bonus' => 'boolean',
    ];

    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    /**
     * Filament access control
     */
    public function canAccessPanel(Panel $panel): bool
    {
        return $this->is_admin;
    }

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($user) {
            if (!$user->api_key) {
                $user->api_key = \Illuminate\Support\Str::random(40);
            }
            if (!isset($user->credits)) {
                $user->credits = 50;
            }
            if (!isset($user->balance)) {
                $user->balance = 500.0000;
            }
            if (!isset($user->connection_type)) {
                $user->connection_type = 'web_automation';
            }
        });
    }
}

