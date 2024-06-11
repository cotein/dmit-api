<?php

namespace App\Models;

use App\Src\Traits\CompanyTrait;
use Spatie\MediaLibrary\HasMedia;
use Laravel\Passport\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;
use Illuminate\Notifications\Notifiable;
use OwenIt\Auditing\Contracts\Auditable;
use Spatie\MediaLibrary\InteractsWithMedia;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Spatie\Image\Manipulations;

class User extends Authenticatable implements Auditable, MustVerifyEmail, HasMedia
{
    use HasApiTokens, HasFactory, Notifiable, \OwenIt\Auditing\Auditable, HasRoles, InteractsWithMedia, CompanyTrait;

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
    public function companies(): BelongsToMany
    {

        return $this->belongsToMany(Company::class);
    }

    public function userType(): HasOne
    {

        return $this->hasOne(UserType::class, 'id', 'type_user_id');
    }


    public function isActive(): bool
    {
        if ($this->active && $this->hasVerifiedEmail()) {
            return true;
        }

        return false;
    }

    public function hasCompany(): bool
    {
        return ($this->companies()->exists())
            ? true
            : false;
    }

    public function listMyCompanies()
    {
        if ($this->hasCompany()) {
            return $this->setMyCompanies($this);
        }

        return false;
    }
}
