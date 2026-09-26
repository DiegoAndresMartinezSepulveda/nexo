<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
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

    public function test_android_image_link_uses_a_short_lived_token_without_a_bearer_header(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();
        $token = $user->createToken('nexo-android')->plainTextToken;
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9Wl6mS8AAAAASUVORK5CYII=');

        $this->withHeader('Authorization', 'Bearer '.$token);
        $id = $this->post('/api/media', [
            'file' => UploadedFile::fake()->createWithContent('captura.png', $png),
        ], ['Accept' => 'application/json'])->assertCreated()->json('id');
        $assetToken = $this->getJson('/api/mobile/asset-token')->assertOk()->json('token');

        $this->flushHeaders();
        $this->get('/api/media/'.$id.'?preview=1&asset_token=invalid', [
            'Accept' => 'application/json',
        ])->assertUnauthorized();
        $this->get('/api/media/'.$id.'?preview=1&asset_token='.$assetToken)
            ->assertOk()
            ->assertHeader('Content-Type', 'image/png');
    }
}
