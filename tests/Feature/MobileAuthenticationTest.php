<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MobileAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_android_login_returns_a_token_and_can_reuse_it(): void
    {
        $user = User::factory()->create([
            'email' => 'android@example.test',
            'password' => bcrypt('UnaClaveSegura123!'),
        ]);

        $response = $this->postJson('/api/mobile/login', [
            'email' => $user->email,
            'password' => 'UnaClaveSegura123!',
        ])->assertOk()->assertJsonPath('user.id', $user->id);

        $token = $response->json('token');
        $this->assertIsString($token);
        $this->assertNotEmpty($token);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/mobile/session')
            ->assertOk()
            ->assertJsonPath('user.email', $user->email);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/logout')
            ->assertNoContent();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/mobile/session')
            ->assertUnauthorized();
    }
}
