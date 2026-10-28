<?php

namespace Tests\Feature;

use App\Helpers\Helper;
use App\Models\User;
use App\Services\VerificationEmailService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Tests\TestCase;

class AccountVerificationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'verification_test',
            'database.connections.verification_test' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => ''],
            'cache.default' => 'array', 'telescope.enabled' => false]);
        \Laravel\Telescope\Telescope::stopRecording();
        \Illuminate\Support\Facades\RateLimiter::swap(new \Illuminate\Cache\RateLimiter(
            new \Illuminate\Cache\Repository(new \Illuminate\Cache\ArrayStore())
        ));
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            foreach (['name', 'email', 'password', 'verification_token', 'role', 'profile_picture', 'mobile', 'country_code'] as $field) {
                $table->string($field)->nullable();
            }
            $table->boolean('is_verified')->default(false);
            $table->integer('parent_user_id')->default(0);
            $table->integer('login_type')->default(1);
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamps();
        });
        Schema::create('personal_access_tokens', function (Blueprint $table) {
            $table->id();
            $table->morphs('tokenable');
            $table->string('name');
            $table->string('token', 64)->unique();
            $table->text('abilities')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });
    }

    private function account(bool $verified = false): User
    {
        return User::create(['name' => 'Admin', 'email' => 'admin@example.com',
            'password' => Hash::make('correct-password'), 'role' => 'ADMIN',
            'is_verified' => $verified, 'parent_user_id' => 0]);
    }

    public function test_invited_members_and_subadmins_are_created_verified(): void
    {
        foreach (['MEMBER', 'SUBADMIN'] as $role) {
            $user = Helper::createNewUser('Invite', strtolower($role).'@example.com', 'password', 1, $role, 38);
            $this->assertSame(1, (int) $user->fresh()->is_verified);
        }
    }

    public function test_self_registered_password_admin_still_requires_verification(): void
    {
        $user = Helper::createNewUser('Admin', 'new@example.com', 'password', 1, 'ADMIN');
        $this->assertSame(0, (int) $user->fresh()->is_verified);
    }

    public function test_unverified_admin_login_sends_email_and_does_not_issue_a_token(): void
    {
        $user = $this->account();
        $service = Mockery::mock(VerificationEmailService::class);
        $service->shouldReceive('send')->once()->with(Mockery::on(fn ($u) => $u->id === $user->id))->andReturn(true);
        $this->app->instance(VerificationEmailService::class, $service);
        $this->postJson('/api/v1/website/auth/login', ['email' => $user->email, 'password' => 'correct-password'])
            ->assertOk()->assertJsonPath('data.is_verified', false)->assertJsonMissingPath('data.token');
        $this->assertSame(0, $user->tokens()->count());
        $this->assertSame(0, (int) $user->fresh()->is_verified);
    }

    public function test_incorrect_password_does_not_send_a_verification_email(): void
    {
        $user = $this->account();
        $service = Mockery::mock(VerificationEmailService::class);
        $service->shouldNotReceive('send');
        $this->app->instance(VerificationEmailService::class, $service);
        $this->postJson('/api/v1/website/auth/login', ['email' => $user->email, 'password' => 'wrong'])
            ->assertJsonPath('message', 'Incorrect password.');
    }

    public function test_verified_account_logs_in_without_sending_verification_email(): void
    {
        $user = $this->account(true);
        $service = Mockery::mock(VerificationEmailService::class);
        $service->shouldNotReceive('send');
        $this->app->instance(VerificationEmailService::class, $service);
        $this->postJson('/api/v1/website/auth/login', ['email' => $user->email, 'password' => 'correct-password'])
            ->assertJsonPath('status_code', 1)->assertJsonStructure(['data' => ['token']]);
    }

    public function test_send_failure_does_not_claim_a_verification_email_was_sent(): void
    {
        $user = $this->account();
        $service = Mockery::mock(VerificationEmailService::class);
        $service->shouldReceive('send')->once()->andReturn(false);
        $this->app->instance(VerificationEmailService::class, $service);
        $response = $this->postJson('/api/v1/website/auth/login', ['email' => $user->email, 'password' => 'correct-password']);
        $this->assertStringContainsString('could not send', $response->json('message'));
        $response->assertJsonMissingPath('data.token');
    }

    public function test_login_email_has_a_working_link_and_retries_preserve_it_and_limit_sends(): void
    {
        $user = $this->account();
        $service = Mockery::mock(VerificationEmailService::class)->makePartial()->shouldAllowMockingProtectedMethods();
        $service->shouldReceive('deliver')->once()->with($user, Mockery::on(function ($html) use ($user) {
            $token = $user->fresh()->verification_token;
            return strlen($token) === 40 && str_contains($html, '/auth/verify-email/'.$token.'/'.$user->id);
        }))->andReturn(true);
        $this->assertTrue($service->send($user));
        $token = $user->fresh()->verification_token;
        $this->assertTrue($service->send($user));
        $this->assertSame($token, $user->fresh()->verification_token);
        $this->get('/api/v1/website/auth/verify-email/'.$token.'/'.$user->id)->assertRedirect();
        $this->assertSame(1, (int) $user->fresh()->is_verified);
    }

    public function test_existing_verification_link_is_reused(): void
    {
        $user = $this->account();
        $user->update(['verification_token' => str_repeat('a', 40)]);
        $service = Mockery::mock(VerificationEmailService::class)->makePartial()->shouldAllowMockingProtectedMethods();
        $service->shouldReceive('deliver')->once()->andReturn(true);
        $this->assertTrue($service->send($user));
        $this->assertSame(str_repeat('a', 40), $user->fresh()->verification_token);
    }

    public function test_failed_delivery_can_be_retried_immediately(): void
    {
        $user = $this->account();
        $service = Mockery::mock(VerificationEmailService::class)->makePartial()->shouldAllowMockingProtectedMethods();
        $service->shouldReceive('deliver')->once()->andReturn(false);
        $this->assertFalse($service->send($user));
        $retry = Mockery::mock(VerificationEmailService::class)->makePartial()->shouldAllowMockingProtectedMethods();
        $retry->shouldReceive('deliver')->once()->andReturn(true);
        $this->assertTrue($retry->send($user));
    }
}
