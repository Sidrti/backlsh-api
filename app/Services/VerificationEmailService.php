<?php

namespace App\Services;

use App\Helpers\Helper;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Throwable;

class VerificationEmailService
{
    public function send(User $user): bool
    {
        $key = 'login-verification-email:'.$user->id;
        if (RateLimiter::tooManyAttempts($key, 1)) {
            return true; // A successful send in the last minute remains usable.
        }

        try {
            // Preserve links already in the user's inbox when they retry login.
            if (!$user->verification_token) {
                $user->verification_token = Str::random(40);
                $user->save();
            }

            $link = url('/api/v1/website/auth/verify-email/'.$user->verification_token.'/'.$user->id);
            $body = view('email.verification_email', ['name' => $user->name, 'link' => $link])->render();
            if ($this->deliver($user, $body) !== true) {
                return false;
            }

            RateLimiter::hit($key, 60);
            return true;
        } catch (Throwable $exception) {
            Log::warning('Login verification email failed', ['user_id' => $user->id]);
            return false;
        }
    }

    protected function deliver(User $user, string $body): bool
    {
        return Helper::sendEmail($user->email, 'Verify your email', $body, $user->name) === true;
    }
}
