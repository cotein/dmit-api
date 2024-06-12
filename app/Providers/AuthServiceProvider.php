<?php

namespace App\Providers;

// use Illuminate\Support\Facades\Gate;
use Laravel\Passport\Passport;
use Tymon\JWTAuth\Facades\JWTAuth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    /*   protected $policies = [
        'App\Models\Model' => 'App\Policies\ModelPolicy',
    ];
 */
    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        $this->registerPolicies();

        Passport::tokensExpireIn(now()->addDays(1));
        Passport::refreshTokensExpireIn(now()->addDays(30));
        Passport::personalAccessTokensExpireIn(now()->addMonths(6));

        VerifyEmail::toMailUsing(function ($notifiable, $url) {
            /* $query = parse_url($url, PHP_URL_QUERY);
            parse_str($query, $params);
            $signature = 'signature=' . $params['signature'];
            log::info('Token: ' . $query);
            // Obtén el ID del usuario
            $userId = $notifiable->getKey();
            $email = $notifiable->getEmailForVerification();
            Log::info('Email: ' . $email);
            // Genera el hash
            $hash = sha1($email);
            Log::info('Hash: ' . $hash);
            // Construye la nueva URL
            $spaUrl = env('CORS_ALLOW_ORIGIN') . '/email/verify/' . $userId . '/' . $hash . '?' . $signature;
 */
            // Generar la URL firmada
            $query = parse_url($url, PHP_URL_QUERY);
            parse_str($query, $params);
            $signature = 'signature=' . $params['signature'];

            $email = $notifiable->getEmailForVerification();
            // Genera el hash
            $hash = sha1($email);

            URL::forceRootUrl(env('CORS_ALLOW_ORIGIN'));

            // Generar la URL firmada
            $url = URL::signedRoute(
                'verification.verify',
                [
                    'id' => $notifiable->getKey(),
                    'hash' => sha1($notifiable->getEmailForVerification()),
                ],
                now()->addMinutes(120)
            );

            // Create the verification URL with the JWT token
            $url = env('CORS_ALLOW_ORIGIN') . '/email/verify/' . $notifiable->getKey() . '/' . $hash . '?' . $signature;


            return (new MailMessage)
                ->subject('Verificación de correo electŕonico.')
                ->salutation('Saludos, DMIT')
                ->greeting('Bienvenido a nuestro Sistema de Facturación Online')
                ->line('Presione el botón de abajo para verificar su correo electrónico, así su cuenta quedará activa.')
                ->action('Verificación de correo electŕonico.', $url);
        });
    }
}
