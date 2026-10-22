<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TurnstileService
{
    protected ?string $secretKey;
    const VERIFY_URL = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';

    public function __construct()
    {
        $this->secretKey = config('services.turnstile.secret_key') ?: env('TURNSTILE_SECRET_KEY');
    }

    /**
     * Verify Turnstile token with Cloudflare.
     *
     * @param string|null $token
     * @param string|null $remoteIp
     * @return bool
     */
    public function verify(?string $token, ?string $remoteIp = null): bool
    {
        if (empty($token)) {
            Log::warning('Turnstile verification failed: Empty token provided.');
            return false;
        }

        if (empty($this->secretKey)) {
            Log::warning('Turnstile secret key is not configured.');
            return false;
        }

        try {
            $payload = [
                'secret' => $this->secretKey,
                'response' => $token,
            ];

            if ($remoteIp) {
                $payload['remoteip'] = $remoteIp;
            }

            $response = Http::asForm()->timeout(10)->post(self::VERIFY_URL, $payload);

            if ($response->successful()) {
                $data = $response->json();
                if (!empty($data['success'])) {
                    return true;
                }

                Log::warning('Turnstile verification failed from Cloudflare', [
                    'errors' => $data['error-codes'] ?? [],
                    'remoteip' => $remoteIp
                ]);
                return false;
            }

            Log::error('Cloudflare Turnstile verification HTTP error', [
                'status' => $response->status(),
                'body' => $response->body()
            ]);
            return false;
        } catch (\Throwable $e) {
            Log::error('Exception verifying Turnstile token: ' . $e->getMessage(), [
                'exception' => $e
            ]);
            return false;
        }
    }
}
