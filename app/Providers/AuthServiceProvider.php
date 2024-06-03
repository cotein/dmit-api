<?php

namespace App\Providers;

// use Illuminate\Support\Facades\Gate;
use Laravel\Passport\Passport;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Log;

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
        Passport::tokensExpireIn(now()->addDays(1));
        Passport::refreshTokensExpireIn(now()->addDays(30));
        Passport::personalAccessTokensExpireIn(now()->addMonths(6));

        VerifyEmail::toMailUsing(function ($notifiable, $url) {

            //$url = substr($url, 21);
            //$spaUrl = "http://localhost:5173/email/verify?email_verify_url=" . $url;
            //$spaUrl = env('CORS_ALLOW_ORIGIN') . "/email/verify?email_verify_url=" . $url;
            $newDomain = env('CORS_ALLOW_ORIGIN');

            // Extrae el dominio original de la URL
            $originalDomain = parse_url($url, PHP_URL_SCHEME) . '://' . parse_url($url, PHP_URL_HOST);

            // Reemplaza el dominio original con el nuevo dominio
            $spaUrl = str_replace($originalDomain, $newDomain, $url);

            $spaUrl = str_replace(':8001', '', $spaUrl);

            return (new MailMessage)
                ->subject('Verificación de correo electŕonico.')
                ->salutation('Saludos, DMIT')
                ->greeting('Bienvenido a nuestro Sistema de Facturación Online')
                ->line('Presione el botón de abajo para verificar su correo electrónico, así su cuenta quedará activa.')
                ->action('Verificación de correo electŕonico.', $spaUrl);
        });
    }
}
