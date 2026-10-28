<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Mockery;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    private function credentials(): array
    {
        return ['email' => 'reset@example.com', 'token' => 'reset-token',
            'password' => 'new-password', 'password_confirmation' => 'new-password'];
    }

    public function test_reset_is_public_and_updates_password_and_revokes_sessions()
    {
        $user = Mockery::mock(User::class)->makePartial();
        $user->shouldReceive('save')->once()->andReturn(true);
        $user->shouldReceive('tokens->delete')->once();
        $broker = Mockery::mock();
        Password::shouldReceive('broker')->once()->andReturn($broker);
        $broker->shouldReceive('reset')->once()->with($this->credentials(), Mockery::on(function ($callback) use ($user) {
            $callback($user, 'new-password');
            $this->assertTrue(Hash::check('new-password', $user->password));
            $this->assertSame(60, strlen($user->getRememberToken()));
            return true;
        }))->andReturn(Password::PASSWORD_RESET);

        $this->postJson('/api/v1/website/auth/forget-password/change-password', $this->credentials())
            ->assertOk()->assertJson(['status_code' => 1])->assertJsonMissingPath('data.token');
    }

    public function test_invalid_expired_or_used_link_returns_a_recoverable_error()
    {
        $broker = Mockery::mock();
        Password::shouldReceive('broker')->once()->andReturn($broker);
        $broker->shouldReceive('reset')->once()->andReturn(Password::INVALID_TOKEN);
        $this->postJson('/api/v1/website/auth/forget-password/change-password', $this->credentials())
            ->assertStatus(422)->assertJson(['status_code' => 2]);
    }

    public function test_confirmation_must_match()
    {
        Password::shouldReceive('broker')->never();
        $data = $this->credentials();
        $data['password_confirmation'] = 'different-password';
        $this->postJson('/api/v1/website/auth/forget-password/change-password', $data)
            ->assertStatus(422)->assertJsonValidationErrors('password');
    }

    public function test_old_signup_style_reset_link_redirects_to_request_a_new_link()
    {
        $this->get('/api/v1/website/auth/forget-password/verify-email/old-token/1')
            ->assertRedirect(rtrim(config('app.website_url'), '/') . '/forget-password?expired=1');
    }
}
