<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

class UserTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function can_get_all_users(): void
    {
        $users = User::factory(4)->create();

        $response = $this->getJson(route('users.index'));
        /** crear la autenticación para la api */
        $response->dump();
    }
}
