<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Tests\TestCase;

class LoginProtectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_web_login_requires_a_one_time_captcha_after_a_bad_password(): void
    {
        $user = User::factory()->create(['password' => bcrypt('UnaClaveSegura123!')]);

        $challenge = $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'incorrecta',
        ])->assertUnprocessable()
            ->assertJsonPath('captcha_required', true)
            ->json();

        preg_match('/(\d+) \+ (\d+)/', $challenge['captcha_question'], $numbers);
        $answer = (string) ((int) $numbers[1] + (int) $numbers[2]);

        $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'UnaClaveSegura123!',
            'captcha_id' => $challenge['captcha_id'],
            'captcha_answer' => $answer,
        ])->assertOk()->assertJsonPath('user.id', $user->id);
    }

    public function test_bad_captcha_is_one_time_and_returns_a_new_challenge(): void
    {
        $user = User::factory()->create();
        $challenge = $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'incorrecta',
        ])->assertUnprocessable()->json();

        $next = $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'incorrecta',
            'captcha_id' => $challenge['captcha_id'],
            'captcha_answer' => '999',
        ])->assertUnprocessable()
            ->assertJsonPath('captcha_required', true)
            ->assertJsonPath('errors.captcha_answer.0', 'La verificación no es correcta o venció.')
            ->json();

        $this->assertNotSame($challenge['captcha_id'], $next['captcha_id']);
    }

    public function test_mobile_login_accepts_the_captcha_after_a_bad_password(): void
    {
        $user = User::factory()->create(['password' => bcrypt('UnaClaveSegura123!')]);
        $challenge = $this->postJson('/api/mobile/login', [
            'email' => $user->email,
            'password' => 'incorrecta',
        ])->assertUnprocessable()->json();

        preg_match('/(\d+) \+ (\d+)/', $challenge['captcha_question'], $numbers);

        $this->postJson('/api/mobile/login', [
            'email' => $user->email,
            'password' => 'UnaClaveSegura123!',
            'captcha_id' => $challenge['captcha_id'],
            'captcha_answer' => (string) ((int) $numbers[1] + (int) $numbers[2]),
        ])->assertOk()->assertJsonPath('user.id', $user->id)->assertJsonStructure(['token']);
    }

    public function test_eight_failed_attempts_block_the_ip_across_web_and_mobile_logins(): void
    {
        $this->withoutMiddleware(ThrottleRequests::class);
        $user = User::factory()->create(['password' => bcrypt('UnaClaveSegura123!')]);
        $challenge = null;

        for ($attempt = 1; $attempt <= 8; $attempt++) {
            $payload = ['email' => $user->email, 'password' => 'incorrecta'];

            if ($challenge) {
                preg_match('/(\d+) \+ (\d+)/', $challenge['captcha_question'], $numbers);
                $payload['captcha_id'] = $challenge['captcha_id'];
                $payload['captcha_answer'] = (string) ((int) $numbers[1] + (int) $numbers[2]);
            }

            $uri = $attempt % 2 === 0 ? '/api/mobile/login' : '/api/login';
            $response = $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.18'])
                ->postJson($uri, $payload);

            if ($attempt < 8) {
                $response->assertUnprocessable()->assertJsonPath('captcha_required', true);
                $challenge = $response->json();
            } else {
                $response->assertTooManyRequests()->assertJsonPath('blocked', true);
            }
        }

        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.18'])
            ->postJson('/api/login', [
                'email' => $user->email,
                'password' => 'UnaClaveSegura123!',
            ])->assertTooManyRequests()->assertJsonPath('blocked', true);

        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.19'])
            ->postJson('/api/login', [
                'email' => $user->email,
                'password' => 'UnaClaveSegura123!',
            ])->assertOk()->assertJsonPath('user.id', $user->id);
    }
}
