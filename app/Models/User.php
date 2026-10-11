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
        'last_name',
        'email',
        'password',
        // El registro los manda en User::create: sin esto Eloquent los descartaba
        // en silencio y el usuario quedaba sin apellido y con el type_user_id
        // por defecto de la tabla (1 = USER_ROOT).
        'type_user_id',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        // El secreto del doble factor y los códigos de recuperación jamás salen en
        // un JSON: si algún endpoint serializa el modelo, no se filtran.
        'two_factor_secret',
        'two_factor_recovery_codes',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        // El secreto del doble factor va cifrado con la APP_KEY: si alguien lee la
        // base, no puede generar los códigos. Nunca sale en un JSON (ver $hidden).
        'two_factor_secret' => 'encrypted',
        'two_factor_confirmed_at' => 'datetime',
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

    /**
     * Revoca TODOS los tokens de API del usuario (Passport).
     *
     * Se llama al cambiar la contraseña: si no, el token que ya estaba afuera
     * (el del navegador de otro dispositivo, o el de alguien que entró con la
     * contraseña vieja) sigue sirviendo como si nada hubiera pasado.
     *
     * Devuelve cuántos revocó, para poder dejarlo en el log.
     */
    public function revokeAllApiTokens(): int
    {
        $revoked = 0;

        foreach ($this->tokens as $token) {
            $token->revoke();
            $revoked++;
        }

        return $revoked;
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
