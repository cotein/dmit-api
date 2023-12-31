<?php

namespace App\Models;

use Laravel\Passport\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;
use Illuminate\Notifications\Notifiable;
use OwenIt\Auditing\Contracts\Auditable;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable implements Auditable, MustVerifyEmail
{
    use HasApiTokens, HasFactory, Notifiable, \OwenIt\Auditing\Auditable, HasRoles;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
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
    ];

    /** Relations ship */
    public function company(): BelongsTo
    {

        return $this->belongsTo(Company::class);
    }

    public function myCompany(): ?int
    {
        return $this->company_id;
    }

    public function isActive(): bool
    {
        if ($this->active && $this->hasVerifiedEmail()) {
            return true;
        }

        return false;
    }
}
