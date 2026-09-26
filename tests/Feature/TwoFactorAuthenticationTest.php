<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\TwoFactorAuth;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TwoFactorAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_totp_matches_the_rfc_6238_sha1_vector(): void
    {
        $service = app(TwoFactorAuth::class);
        $secret = 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ';

        $this->assertSame(1, $service->matchingStep($secret, '287082', 59));
        $this->assertNull($service->matchingStep($secret, '000000', 59));
    }

    public function test_web_login_requires_and_accepts_the_authenticator_code(): void
    {
        $user = User::factory()->create(['password' => bcrypt('UnaClaveSegura123!')]);
        $service = app(TwoFactorAuth::class);
        $secret = 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ';
        $user->forceFill([
            'two_factor_secret' => $service->encryptedSecret($secret),
            'two_factor_confirmed_at' => now(),
            'two_factor_last_used_step' => null,
            'two_factor_recovery_codes' => [],
        ])->save();

        $firstCaptcha = $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'incorrecta',
        ])->assertUnprocessable()->assertJsonPath('captcha_required', true)->json();

        preg_match('/(\d+) \+ (\d+)/', $firstCaptcha['captcha_question'], $numbers);
        $passwordOnly = $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'UnaClaveSegura123!',
            'captcha_id' => $firstCaptcha['captcha_id'],
            'captcha_answer' => (string) ((int) $numbers[1] + (int) $numbers[2]),
        ])->assertUnprocessable()->assertJsonPath('two_factor_required', true)
            ->assertJsonPath('captcha_required', true)->json();

        preg_match('/(\d+) \+ (\d+)/', $passwordOnly['captcha_question'], $numbers);
        $badOtp = $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'UnaClaveSegura123!',
            'two_factor_code' => '000000',
            'captcha_id' => $passwordOnly['captcha_id'],
            'captcha_answer' => (string) ((int) $numbers[1] + (int) $numbers[2]),
        ])->assertUnprocessable()->assertJsonPath('two_factor_required', true)
            ->assertJsonPath('captcha_required', true)->json();

        $step = intdiv(now()->timestamp, 30);
        $code = $this->totpCode($secret, $step);
        preg_match('/(\d+) \+ (\d+)/', $badOtp['captcha_question'], $numbers);
        $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'UnaClaveSegura123!',
            'two_factor_code' => $code,
            'captcha_id' => $badOtp['captcha_id'],
            'captcha_answer' => (string) ((int) $numbers[1] + (int) $numbers[2]),
        ])->assertOk()->assertJsonPath('user.two_factor_enabled', true);

        $this->assertAuthenticatedAs($user);
        $this->assertFalse($service->verifyAndConsume($user->fresh(), $code), 'A TOTP code cannot be replayed.');
    }

    public function test_mobile_login_accepts_a_recovery_code_once(): void
    {
        $user = User::factory()->create(['password' => bcrypt('UnaClaveSegura123!')]);
        $service = app(TwoFactorAuth::class);
        $recoveryCode = 'a1b2c3d4e5';
        $user->forceFill([
            'two_factor_secret' => $service->encryptedSecret($service->newSecret()),
            'two_factor_confirmed_at' => now(),
            'two_factor_last_used_step' => null,
            'two_factor_recovery_codes' => $service->hashRecoveryCodes([$recoveryCode]),
        ])->save();

        $this->postJson('/api/mobile/login', [
            'email' => $user->email,
            'password' => 'UnaClaveSegura123!',
        ])->assertUnprocessable()->assertJsonPath('two_factor_required', true);

        $this->postJson('/api/mobile/login', [
            'email' => $user->email,
            'password' => 'UnaClaveSegura123!',
            'two_factor_code' => $recoveryCode,
        ])->assertOk()->assertJsonStructure(['token'])->assertJsonPath('user.two_factor_enabled', true);

        $this->assertSame([], $user->fresh()->two_factor_recovery_codes);
        $this->assertFalse($service->verifyAndConsume($user->fresh(), $recoveryCode), 'A recovery code can only be used once.');
    }

    public function test_a_user_can_activate_two_factor_and_receives_recovery_codes_once(): void
    {
        $user = User::factory()->create(['password' => bcrypt('UnaClaveSegura123!')]);
        $this->actingAs($user);

        $setup = $this->postJson('/api/two-factor/setup', [
            'password' => 'UnaClaveSegura123!',
        ])->assertOk()->assertJsonStructure(['secret', 'otpauth_uri'])->json();

        $this->assertStringStartsWith('otpauth://totp/Nexo%3A', $setup['otpauth_uri']);
        $step = intdiv(now()->timestamp, 30);
        $codes = $this->postJson('/api/two-factor/confirm', [
            'code' => $this->totpCode($setup['secret'], $step),
        ])->assertOk()->assertJsonPath('enabled', true)->json('recovery_codes');

        $this->assertCount(10, $codes);
        $this->assertTrue($user->fresh()->profilePayload()['two_factor_enabled']);
        $this->assertNotSame($setup['secret'], $user->fresh()->two_factor_secret);
        $this->assertSame(10, count($user->fresh()->two_factor_recovery_codes));
    }

    public function test_disabling_two_factor_requires_the_current_password_and_a_valid_code(): void
    {
        $user = User::factory()->create(['password' => bcrypt('UnaClaveSegura123!')]);
        $service = app(TwoFactorAuth::class);
        $secret = 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ';
        $user->forceFill([
            'two_factor_secret' => $service->encryptedSecret($secret),
            'two_factor_confirmed_at' => now(),
            'two_factor_last_used_step' => null,
            'two_factor_recovery_codes' => [],
        ])->save();
        $this->actingAs($user);
        $code = $this->totpCode($secret, intdiv(now()->timestamp, 30));

        $this->postJson('/api/two-factor/disable', [
            'password' => 'incorrecta',
            'code' => $code,
        ])->assertUnprocessable();

        $this->postJson('/api/two-factor/disable', [
            'password' => 'UnaClaveSegura123!',
            'code' => $code,
        ])->assertOk()->assertJsonPath('enabled', false);

        $this->assertFalse((bool) $user->fresh()->profilePayload()['two_factor_enabled']);
    }

    public function test_emergency_server_command_resets_only_the_second_factor(): void
    {
        $user = User::factory()->create(['password' => bcrypt('UnaClaveSegura123!')]);
        $service = app(TwoFactorAuth::class);
        $user->forceFill([
            'two_factor_secret' => $service->encryptedSecret($service->newSecret()),
            'two_factor_confirmed_at' => now(),
            'two_factor_last_used_step' => 12,
            'two_factor_recovery_codes' => $service->hashRecoveryCodes(['a1b2c3d4e5']),
        ])->save();

        $this->artisan('nexo:two-factor-reset', ['email' => $user->email, '--force' => true])->assertSuccessful();

        $this->assertFalse((bool) $user->fresh()->profilePayload()['two_factor_enabled']);
        $this->assertTrue(password_verify('UnaClaveSegura123!', $user->fresh()->password));
    }

    private function totpCode(string $secret, int $step): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $bits = '';
        foreach (str_split($secret) as $character) {
            $bits .= str_pad(decbin(strpos($alphabet, $character)), 5, '0', STR_PAD_LEFT);
        }

        $key = '';
        foreach (str_split($bits, 8) as $chunk) {
            if (strlen($chunk) === 8) {
                $key .= chr(bindec($chunk));
            }
        }

        $hash = hash_hmac('sha1', pack('N2', ($step >> 32) & 0xffffffff, $step & 0xffffffff), $key, true);
        $offset = ord($hash[19]) & 0x0f;
        $value = ((ord($hash[$offset]) & 0x7f) << 24)
            | ((ord($hash[$offset + 1]) & 0xff) << 16)
            | ((ord($hash[$offset + 2]) & 0xff) << 8)
            | (ord($hash[$offset + 3]) & 0xff);

        return str_pad((string) ($value % 1_000_000), 6, '0', STR_PAD_LEFT);
    }
}
