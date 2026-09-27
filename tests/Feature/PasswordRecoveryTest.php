<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\PasswordChangedNotification;
use App\Notifications\PasswordResetNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class PasswordRecoveryTest extends TestCase
{
    use RefreshDatabase;

    public function test_password_reset_request_has_the_same_response_for_known_and_unknown_accounts(): void
    {
        Notification::fake();
        $user = User::factory()->create();

        $known = $this->from('/password/forgot')->post('/password/forgot', ['email' => $user->email]);
        $unknown = $this->from('/password/forgot')->post('/password/forgot', ['email' => 'missing@example.test']);

        $known->assertRedirect('/password/forgot');
        $unknown->assertRedirect('/password/forgot');
        $this->assertSame($known->getSession()->get('status'), $unknown->getSession()->get('status'));
        Notification::assertSentTo($user, PasswordResetNotification::class);
        $this->assertCount(1, Notification::sent($user, PasswordResetNotification::class));
        $notice = Notification::sent($user, PasswordResetNotification::class)->first()->toMail($user);
        $this->assertStringContainsString('/reset-password/', $notice->viewData['actionUrl']);
        $this->assertSame('Recupera tu acceso a Nexo', $notice->subject);
        $this->assertStringContainsString('Crear contraseña nueva', $notice->render());
    }

    public function test_a_valid_single_use_reset_link_changes_the_password_and_revokes_old_access(): void
    {
        Notification::fake();
        $user = User::factory()->create(['password' => 'OldStrongPassword123!']);
        $token = $user->createToken('old-phone');
        $resetToken = Password::createToken($user);

        $this->get(route('password.reset', ['token' => $resetToken, 'email' => $user->email]))
            ->assertOk()
            ->assertSee('Crea una contraseña nueva');

        $this->from(route('password.reset', ['token' => $resetToken, 'email' => $user->email]))
            ->post('/reset-password', [
                'token' => $resetToken,
                'email' => $user->email,
                'password' => 'AnotherStrongPassword456!',
                'password_confirmation' => 'AnotherStrongPassword456!',
            ])
            ->assertOk()
            ->assertSee('La contraseña se actualizó');

        $this->assertTrue(Hash::check('AnotherStrongPassword456!', $user->fresh()->password));
        $this->assertSame(2, (int) $user->fresh()->auth_version);
        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $token->accessToken->id]);
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => $user->email]);
        $this->assertDatabaseHas('audit_logs', [
            'event' => 'security.password.reset',
            'actor_id' => $user->id,
            'actor_email' => $user->email,
        ]);
        Notification::assertSentTo($user, PasswordChangedNotification::class);

        $this->post('/reset-password', [
            'token' => $resetToken,
            'email' => $user->email,
            'password' => 'ThirdStrongPassword456!',
            'password_confirmation' => 'ThirdStrongPassword456!',
        ])->assertUnprocessable();
    }

    public function test_reset_requires_confirmation_and_at_least_twelve_characters(): void
    {
        $user = User::factory()->create();
        $token = Password::createToken($user);

        $this->post('/reset-password', [
            'token' => $token,
            'email' => $user->email,
            'password' => 'short',
            'password_confirmation' => 'different',
        ])->assertSessionHasErrors(['password']);

        $this->assertSame(1, (int) $user->fresh()->auth_version);
    }

    public function test_account_owner_can_change_their_password_and_other_access_is_revoked(): void
    {
        Notification::fake();
        $user = User::factory()->create(['role' => 'admin', 'password' => 'CurrentPassword123!']);
        $user = $user->fresh();
        $oldMobileToken = $user->createToken('old-phone');
        $this->actingAs($user);

        $this->putJson('/api/account/password', [
            'current_password' => 'wrong-password',
            'password' => 'NewPassword456789!',
            'password_confirmation' => 'NewPassword456789!',
        ])->assertUnprocessable();

        $result = $this->putJson('/api/account/password', [
            'current_password' => 'CurrentPassword123!',
            'password' => 'NewPassword456789!',
            'password_confirmation' => 'NewPassword456789!',
        ])->assertOk()->assertJsonPath('message', 'Tu contraseña se actualizó. Las demás sesiones se cerraron.');
        $this->assertTrue(Hash::check('NewPassword456789!', $user->fresh()->password));
        $this->assertSame(2, (int) $user->fresh()->auth_version);
        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $oldMobileToken->accessToken->id]);
        Notification::assertSentTo($user, PasswordChangedNotification::class);
    }

    public function test_a_session_from_before_a_password_change_is_rejected(): void
    {
        $user = User::factory()->create(['auth_version' => 2]);

        $this->actingAs($user)->withSession(['nexo_auth_version' => 1])
            ->getJson('/api/session')
            ->assertUnauthorized()
            ->assertJsonPath('message', 'Tu sesión terminó porque se actualizó la contraseña. Inicia sesión nuevamente.');
    }

    public function test_mobile_password_change_keeps_only_the_device_that_changed_it(): void
    {
        Notification::fake();
        $user = User::factory()->create(['password' => 'CurrentPassword123!'])->fresh();
        $current = $user->createToken('current-phone');
        $other = $user->createToken('other-phone');

        $this->withToken($current->plainTextToken)->putJson('/api/account/password', [
            'current_password' => 'CurrentPassword123!',
            'password' => 'NewPassword456789!',
            'password_confirmation' => 'NewPassword456789!',
        ])->assertOk();

        $this->assertDatabaseHas('personal_access_tokens', ['id' => $current->accessToken->id]);
        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $other->accessToken->id]);
    }
}
