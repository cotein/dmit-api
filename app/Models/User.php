<?php

namespace App\Models;

use Carbon\Carbon;
use App\Mail\ConfirmEmailMail;
use App\Src\Traits\CompanyTrait;
use Spatie\MediaLibrary\HasMedia;
use Laravel\Passport\HasApiTokens;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\Traits\HasRoles;
use Illuminate\Notifications\Notifiable;
use OwenIt\Auditing\Contracts\Auditable;
use Spatie\MediaLibrary\InteractsWithMedia;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\Log;

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

    public function sendEmailVerification($email, $token)
    {
        $client = new \GuzzleHttp\Client();

        $resul = $client->postAsync(env('EMAIL_SENDER_URL') . '/api/email-sender/register-user', [
            'json' => [
                'to' => $email,
                'token' => $token,
            ],
        ]);

        $resul->then(
            function ($response) {
                Log::info('Email sent successfully ' . $response->getBody());
            },
            function ($exception) {
                Log::error('Failed to send email: ' . $exception->getMessage());
            }
        );

        $resul->wait();
    }

    // En App\Models\User.php


    protected function name(): Attribute
    {
        return Attribute::make(
            fn (string $value) => $value,
            fn (string $value) => strtoupper($value),
        );
    }

    protected function lastName(): Attribute
    {
        return Attribute::make(
            fn (string $value) => $value,
            fn (string $value) => strtoupper($value),
        );
    }
}
