<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use App\Models\Subscription;
use Carbon\Carbon;

class LemonSqueezyWebhookController extends Controller
{
    /**
     * Handle LemonSqueezy webhooks
     */
    public function handle(Request $request)
    {
        // Verify the webhook signature (recommended for security)
        if (!$this->verifySignature($request)) {
            Log::warning('LemonSqueezy webhook signature verification failed');
            return response()->json(['error' => 'Invalid signature'], 403);
        }

        $payload = $request->all();
        $eventType = $payload['meta']['event_name'] ?? null;

        Log::info('LemonSqueezy webhook received', [
            'event_type' => $eventType,
            'payload' => $payload
        ]);

        try {
            switch ($eventType) {
                case 'subscription_created':
                    $this->handleSubscriptionCreated($payload);
                    break;

                case 'subscription_updated':
                    $this->handleSubscriptionUpdated($payload);
                    break;

                case 'subscription_cancelled':
                    $this->handleSubscriptionCancelled($payload);
                    break;

                case 'subscription_resumed':
                    $this->handleSubscriptionResumed($payload);
                    break;

                case 'subscription_expired':
                    $this->handleSubscriptionExpired($payload);
                    break;

                case 'subscription_paused':
                    $this->handleSubscriptionPaused($payload);
                    break;

                case 'subscription_unpaused':
                    $this->handleSubscriptionUnpaused($payload);
                    break;

                default:
                    Log::info('Unhandled LemonSqueezy webhook event', ['event_type' => $eventType]);
                    break;
            }

            return response()->json(['status' => 'success']);

        } catch (\Exception $e) {
            Log::error('Error processing LemonSqueezy webhook', [
                'event_type' => $eventType,
                'error' => $e->getMessage(),
                'payload' => $payload
            ]);

            return response()->json(['error' => 'Internal server error'], 500);
        }
    }

    /**
     * Handle subscription created event
     */
    protected function handleSubscriptionCreated($payload)
    {
        $data = $payload['data'];
        $customData = $data['attributes']['custom_data'] ?? [];
        $userId = $customData['user_id'] ?? null;

        if (!$userId) {
            Log::warning('No user_id found in subscription_created webhook', ['payload' => $payload]);
            return;
        }

        $subscription = Subscription::updateOrCreate(
            ['stripe_id' => $data['id']],
            [
                'user_id' => $userId,
                'name' => $data['attributes']['product_name'] ?? 'default',
                'stripe_id' => $data['id'],
                'stripe_status' => $this->mapLemonSqueezyStatus($data['attributes']['status']),
                'stripe_price' => $data['attributes']['variant_name'] ?? null,
                'quantity' => $data['attributes']['quantity'] ?? 1,
                'trial_ends_at' => $data['attributes']['trial_ends_at'] ? 
                    Carbon::parse($data['attributes']['trial_ends_at']) : null,
                'ends_at' => $data['attributes']['ends_at'] ? 
                    Carbon::parse($data['attributes']['ends_at']) : null,
            ]
        );

        Log::info('Subscription created/updated', ['subscription_id' => $subscription->id]);
    }

    /**
     * Handle subscription updated event
     */
    protected function handleSubscriptionUpdated($payload)
    {
        $data = $payload['data'];
        $subscription = Subscription::where('stripe_id', $data['id'])->first();

        if (!$subscription) {
            Log::warning('Subscription not found for update', ['stripe_id' => $data['id']]);
            return;
        }

        $subscription->update([
            'stripe_status' => $this->mapLemonSqueezyStatus($data['attributes']['status']),
            'quantity' => $data['attributes']['quantity'] ?? $subscription->quantity,
            'trial_ends_at' => $data['attributes']['trial_ends_at'] ? 
                Carbon::parse($data['attributes']['trial_ends_at']) : null,
            'ends_at' => $data['attributes']['ends_at'] ? 
                Carbon::parse($data['attributes']['ends_at']) : null,
        ]);

        Log::info('Subscription updated', ['subscription_id' => $subscription->id]);
    }

    /**
     * Handle subscription cancelled event
     */
    protected function handleSubscriptionCancelled($payload)
    {
        $data = $payload['data'];
        $subscription = Subscription::where('stripe_id', $data['id'])->first();

        if (!$subscription) {
            Log::warning('Subscription not found for cancellation', ['stripe_id' => $data['id']]);
            return;
        }

        $subscription->update([
            'stripe_status' => 'canceled',
            'ends_at' => $data['attributes']['ends_at'] ? 
                Carbon::parse($data['attributes']['ends_at']) : Carbon::now(),
        ]);

        Log::info('Subscription cancelled', ['subscription_id' => $subscription->id]);
    }

    /**
     * Handle subscription resumed event
     */
    protected function handleSubscriptionResumed($payload)
    {
        $data = $payload['data'];
        $subscription = Subscription::where('stripe_id', $data['id'])->first();

        if (!$subscription) {
            Log::warning('Subscription not found for resume', ['stripe_id' => $data['id']]);
            return;
        }

        $subscription->update([
            'stripe_status' => 'active',
            'ends_at' => null, // Clear the end date when resumed
        ]);

        Log::info('Subscription resumed', ['subscription_id' => $subscription->id]);
    }

    /**
     * Handle subscription expired event
     */
    protected function handleSubscriptionExpired($payload)
    {
        $data = $payload['data'];
        $subscription = Subscription::where('stripe_id', $data['id'])->first();

        if (!$subscription) {
            Log::warning('Subscription not found for expiration', ['stripe_id' => $data['id']]);
            return;
        }

        $subscription->update([
            'stripe_status' => 'expired',
            'ends_at' => $data['attributes']['ends_at'] ? 
                Carbon::parse($data['attributes']['ends_at']) : Carbon::now(),
        ]);

        Log::info('Subscription expired', ['subscription_id' => $subscription->id]);
    }

    /**
     * Handle subscription paused event
     */
    protected function handleSubscriptionPaused($payload)
    {
        $data = $payload['data'];
        $subscription = Subscription::where('stripe_id', $data['id'])->first();

        if (!$subscription) {
            Log::warning('Subscription not found for pause', ['stripe_id' => $data['id']]);
            return;
        }

        $subscription->update([
            'stripe_status' => 'paused',
        ]);

        Log::info('Subscription paused', ['subscription_id' => $subscription->id]);
    }

    /**
     * Handle subscription unpaused event
     */
    protected function handleSubscriptionUnpaused($payload)
    {
        $data = $payload['data'];
        $subscription = Subscription::where('stripe_id', $data['id'])->first();

        if (!$subscription) {
            Log::warning('Subscription not found for unpause', ['stripe_id' => $data['id']]);
            return;
        }

        $subscription->update([
            'stripe_status' => 'active',
        ]);

        Log::info('Subscription unpaused', ['subscription_id' => $subscription->id]);
    }

    /**
     * Map LemonSqueezy status to your local status format
     */
    protected function mapLemonSqueezyStatus($lemonSqueezyStatus)
    {
        $statusMap = [
            'on_trial' => 'trialing',
            'active' => 'active',
            'paused' => 'paused',
            'past_due' => 'past_due',
            'unpaid' => 'unpaid',
            'cancelled' => 'canceled',
            'expired' => 'expired',
        ];

        return $statusMap[$lemonSqueezyStatus] ?? $lemonSqueezyStatus;
    }

    /**
     * Verify webhook signature for security
     * You should get the webhook secret from LemonSqueezy dashboard
     */
    protected function verifySignature(Request $request)
    {
        $signature = $request->header('X-Signature');
        $secret = config('services.lemonsqueezy.webhook_secret'); // Add this to your config

        if (!$signature || !$secret) {
            return false; // You might want to return true during development
        }

        $payload = $request->getContent();
        $expectedSignature = hash_hmac('sha256', $payload, $secret);

        return hash_equals($signature, $expectedSignature);
    }
}