<?php

namespace Tests\Unit;

use App\Models\Company;
use App\Models\User;
use App\Src\Support\CurrentCompany;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_user_can_belong_to_more_than_one_company(): void
    {
        $user = User::factory()->create();
        $companies = Company::factory(2)->create();

        $user->companies()->attach($companies->pluck('id'));

        $this->assertCount(2, $user->fresh()->companies);
        $this->assertTrue($user->hasCompany());
    }

    public function test_is_active_requires_the_active_flag_and_a_verified_email(): void
    {
        $inactive = User::factory()->create(['active' => false, 'email_verified_at' => now()]);
        $unverified = User::factory()->create(['active' => true, 'email_verified_at' => null]);
        $active = User::factory()->create(['active' => true, 'email_verified_at' => now()]);

        $this->assertFalse($inactive->isActive());
        $this->assertFalse($unverified->isActive());
        $this->assertTrue($active->isActive());
    }

    public function test_without_an_authenticated_user_there_is_no_active_company(): void
    {
        // Contrato del que depende CompanyScope (fail-closed): sin sesión no hay
        // empresa activa, y por lo tanto los modelos con el trait no ven filas.
        $this->assertNull(app(CurrentCompany::class)->id());
    }
}
