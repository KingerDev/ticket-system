<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'is_super_admin',
        'hide_seating',
    ];

    /** Super administrátor smie spravovať používateľov a čítať auditný log. */
    public function isSuperAdmin(): bool
    {
        return (bool) $this->is_super_admin;
    }

    /**
     * Či smie vidieť, kto kde sedí, a usádzať. Super admin vždy –
     * inak by si mohol zamknúť konfiguráciu sály.
     */
    public function canSeeSeating(): bool
    {
        return $this->isSuperAdmin() || ! $this->hide_seating;
    }

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        // Rola sa do prehliadača neposiela, používateľ ju nemá vidieť.
        'hide_seating',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_super_admin' => 'boolean',
            'hide_seating' => 'boolean',
        ];
    }
}
