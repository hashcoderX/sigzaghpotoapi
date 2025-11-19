<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'privilege',
        'parent_user_id',
        'phone',
        'whatsapp',
        'website',
        'country',
        'address',
        'currency',
        'payment_provider',
        'payment_customer_id',
        'trial_started_at',
        'trial_ends_at',
        'logo_path',
        'cover_path',
        'active',
        'premium_package',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
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
            'active' => 'boolean',
            'premium_package' => 'boolean',
            'trial_started_at' => 'datetime',
            'trial_ends_at' => 'datetime',
        ];
    }

    public function parent()
    {
        return $this->belongsTo(User::class, 'parent_user_id');
    }

    public function children()
    {
        return $this->hasMany(User::class, 'parent_user_id');
    }
}
