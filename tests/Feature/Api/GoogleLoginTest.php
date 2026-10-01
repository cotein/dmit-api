<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Passport\ClientRepository;
use Laravel\Passport\Passport;
use Tests\TestCase;

/**
 * Regresión del agujero de seguridad: /auth/google confiaba en el email y el sub
 * que llegaban en el body, así que cualquiera podía pedir un token para la cuenta
 * de otra persona. Ahora exige y valida el id_token de Google.
 */
class GoogleLoginTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.google.client_id' => 'test-client-id.apps.googleusercontent.com']);

        // createToken() necesita un personal access client (en producción lo crea
        // passport:install en el entrypoint del contenedor).
        if (! Passport::personalAccessClient()->exists()) {
            app(ClientRepository::class)->createPersonalAccessClient(null, 'Testing Personal Access Client', 'http://localhost');
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function googlePayload(array $overrides = []): array
    {
        return array_merge([
            'aud' => 'test-client-id.apps.googleusercontent.com',
            'iss' => 'accounts.google.com',
            'email_verified' => 'true',
            'sub' => 'google-sub-123',
            'email' => 'persona@example.com',
            'given_name' => 'Ana',
            'family_name' => 'Perez',
        ], $overrides);
    }

    public function test_the_legacy_body_cannot_log_in_as_an_existing_user(): void
    {
        $victima = User::factory()->create([
            'email' => 'victima@example.com',
            'active' => true,
            'email_verified_at' => now(),
        ]);

        // El ataque viejo: mandar el email de otro y un sub cualquiera.
        $this->postJson('/auth/google', [
            'email' => 'victima@example.com',
            'sub' => 'sub-inventado',
            'given_name' => 'A',
            'family_name' => 'B',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['id_token', 'credential'])
            ->assertJsonMissing(['userToken']);

        $this->assertNull($victima->fresh()->google_id);
    }

    public function test_it_rejects_a_token_issued_for_another_application(): void
    {
        Http::fake([
            'oauth2.googleapis.com/tokeninfo*' => Http::response($this->googlePayload([
                'aud' => 'otra-app.apps.googleusercontent.com',
            ])),
        ]);

        User::factory()->create([
            'email' => 'persona@example.com',
            'google_id' => 'google-sub-123',
            'active' => true,
            'email_verified_at' => now(),
        ]);

        $this->postJson('/auth/google', ['id_token' => 'token-de-otra-app'])
            ->assertStatus(400)
            ->assertJsonMissing(['userToken']);
    }

    public function test_it_rejects_a_token_whose_email_is_not_verified(): void
    {
        Http::fake([
            'oauth2.googleapis.com/tokeninfo*' => Http::response($this->googlePayload([
                'email_verified' => 'false',
            ])),
        ]);

        $this->postJson('/auth/google', ['id_token' => 'token-sin-email-verificado'])
            ->assertStatus(400)
            ->assertJsonMissing(['userToken']);
    }

    public function test_it_logs_in_an_existing_user_with_a_valid_token(): void
    {
        Http::fake([
            'oauth2.googleapis.com/tokeninfo*' => Http::response($this->googlePayload()),
        ]);

        $user = User::factory()->create([
            'email' => 'persona@example.com',
            'google_id' => 'google-sub-123',
            'active' => true,
            'email_verified_at' => now(),
        ]);

        $this->postJson('/auth/google', ['credential' => 'token-valido'])
            ->assertOk()
            ->assertJsonPath('user.id', $user->id)
            ->assertJsonStructure(['user', 'userToken' => ['access_token', 'token_type']]);
    }

    public function test_it_creates_the_user_from_the_verified_google_payload(): void
    {
        Http::fake([
            'oauth2.googleapis.com/tokeninfo*' => Http::response($this->googlePayload([
                'email' => 'nueva@example.com',
                'sub' => 'google-sub-nueva',
                'given_name' => 'Ana',
                'family_name' => 'Perez',
            ])),
        ]);

        $this->postJson('/auth/google', ['id_token' => 'token-valido'])
            ->assertOk()
            ->assertJsonStructure(['user', 'userToken' => ['access_token']]);

        $this->assertDatabaseHas('users', [
            'email' => 'nueva@example.com',
            'google_id' => 'google-sub-nueva',
            'name' => 'ANA',
            'last_name' => 'PEREZ',
            'active' => true,
        ]);

        $this->assertNotNull(User::where('email', 'nueva@example.com')->first()->email_verified_at);
    }
}
