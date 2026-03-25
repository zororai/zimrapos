<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'plan',
        'trial_ends_at',
        'subscribed_at',
        'company_name',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'trial_ends_at'     => 'datetime',
            'subscribed_at'     => 'datetime',
            'password'          => 'hashed',
        ];
    }

    public function zimraConfigs()
    {
        return $this->hasMany(ZimraConfig::class);
    }

    public function onTrial(): bool
    {
        return $this->plan === 'trial'
            && $this->trial_ends_at
            && $this->trial_ends_at->isFuture();
    }

    public function subscribed(): bool
    {
        return in_array($this->plan, ['basic', 'pro']) && $this->subscribed_at !== null;
    }

    public function hasAccess(): bool
    {
        return $this->onTrial() || $this->subscribed();
    }

    public function trialDaysLeft(): int
    {
        if (!$this->trial_ends_at) {
            return 0;
        }
        return max(0, (int) now()->diffInDays($this->trial_ends_at, false));
    }
}
