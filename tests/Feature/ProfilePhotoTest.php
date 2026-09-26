<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProfilePhotoTest extends TestCase
{
    use RefreshDatabase;

    private function tinyPng(): UploadedFile
    {
        $bytes = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9Wl6mS8AAAAASUVORK5CYII=');

        return UploadedFile::fake()->createWithContent('profile.png', $bytes);
    }

    public function test_people_can_change_their_own_private_profile_photo_and_admin_can_manage_members(): void
    {
        Storage::fake('local');
        $admin = User::factory()->create(['role' => 'admin']);
        $member = User::factory()->create(['role' => 'editor']);

        $this->actingAs($admin)
            ->post('/api/users/'.$member->id.'/photo', ['photo' => $this->tinyPng()], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('profile_photo_url', '/api/users/'.$member->id.'/photo');

        $path = $member->fresh()->profile_photo_path;
        $this->assertNotEmpty($path);
        Storage::disk('local')->assertExists($path);

        $this->get('/api/users/'.$member->id.'/photo')->assertOk()->assertHeader('Content-Type', 'image/png');

        $this->actingAs($member)
            ->deleteJson('/api/users/'.$member->id.'/photo')
            ->assertNoContent();

        $this->assertNull($member->fresh()->profile_photo_path);
        Storage::disk('local')->assertMissing($path);
    }

    public function test_users_cannot_read_or_change_an_unrelated_persons_profile_photo(): void
    {
        Storage::fake('local');
        $person = User::factory()->create();
        $other = User::factory()->create();

        $this->actingAs($other)
            ->post('/api/users/'.$person->id.'/photo', ['photo' => $this->tinyPng()], ['Accept' => 'application/json'])
            ->assertForbidden();

        $this->get('/api/users/'.$person->id.'/photo')->assertNotFound();
    }

    public function test_profile_photo_accepts_only_supported_images(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();
        $this->actingAs($user)
            ->post('/api/users/'.$user->id.'/photo', [
                'photo' => UploadedFile::fake()->createWithContent('profile.svg', '<svg xmlns="http://www.w3.org/2000/svg"></svg>'),
            ], ['Accept' => 'application/json'])
            ->assertUnprocessable();
    }

    public function test_android_can_load_a_profile_photo_through_a_short_lived_asset_token(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();
        $accessToken = $user->createToken('nexo-android')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$accessToken)
            ->post('/api/users/'.$user->id.'/photo', ['photo' => $this->tinyPng()], ['Accept' => 'application/json'])
            ->assertOk();

        $assetToken = $this->withHeader('Authorization', 'Bearer '.$accessToken)
            ->getJson('/api/mobile/asset-token')
            ->assertOk()
            ->json('token');

        $this->flushHeaders();
        $this->get('/api/users/'.$user->id.'/photo?asset_token='.$assetToken)
            ->assertOk()
            ->assertHeader('Content-Type', 'image/png');
    }
}
