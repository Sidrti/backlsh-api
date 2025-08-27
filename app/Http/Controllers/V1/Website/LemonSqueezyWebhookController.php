<?php

namespace App\Http\Controllers\V1\Website;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
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
     //   if (!$this->verifySignature($request)) {
        //    return response()->json(['error' => 'Invalid signature'], 403);
      //  }

        $payload = $request->all();
        $eventType = $payload['meta']['event_name'] ?? null;

        try {
            switch ($eventType) {
                case 'subscription_created':
                    $this->handleSubscriptionCreated($payload);
                    break;

                case 'subscription_updated':
                  //  $this->handleSubscriptionUpdated($payload);
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
                   // $this->handleSubscriptionPaused($payload);
                    break;

                case 'subscription_unpaused':
                   // $this->handleSubscriptionUnpaused($payload);
                    break;

                default:
                    break;
            }

            return response()->json(['status' => 'success']);

        } catch (\Exception $e) {

            return response()->json(['error' => 'Internal server error'], 500);
        }
    }

    /**
     * Handle subscription created event
     */
  protected function handleSubscriptionCreated($payload)
{
    $data = $payload['data'];
    $attributes = $data['attributes'];
    $customData = $payload['meta']['custom_data'] ?? [];
    $userId = $customData['user_id'] ?? null;

    if (!$userId) {
        return;
    }

    $subscription = Subscription::updateOrCreate(
        ['stripe_id' => $data['id']], // using stripe_id column to store LemonSqueezy subscription ID
          [
                'user_id' => $userId,
                'name' => $data['attributes']['product_name'] ?? 'default',
                'stripe_id' => $data['id'],
                'stripe_status' => $this->mapLemonSqueezyStatus($data['attributes']['status']),
                'stripe_price' => $data['attributes']['variant_name'] ?? null,
                'quantity' => $attributes['first_subscription_item']['quantity'] ?? 1,
                'ends_at' => $data['attributes']['renews_at'] ? 
                    Carbon::parse($data['attributes']['renews_at']) : '',
                'stripe_price' => config('app.unit_price') * ($attributes['first_subscription_item']['quantity'] ?? 1)
            ]
    );
}


    /**
     * Handle subscription updated event
     */
    protected function handleSubscriptionUpdated($payload)
    {
        $data = $payload['data'];
        $subscription = Subscription::where('stripe_id', $data['id'])->first();

        if (!$subscription) {
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
    }

    /**
     * Handle subscription cancelled event
     */
    protected function handleSubscriptionCancelled($payload)
    {
        $data = $payload['data'];
        $subscription = Subscription::where('stripe_id', $data['id'])->first();

        if (!$subscription) {
            return;
        }

        $subscription->update([
            'stripe_status' => 'canceled',
            'ends_at' => $data['attributes']['ends_at'] ? 
                Carbon::parse($data['attributes']['ends_at']) : Carbon::now(),
        ]);
    }

    /**
     * Handle subscription resumed event
     */
    protected function handleSubscriptionResumed($payload)
    {
        $data = $payload['data'];
        $subscription = Subscription::where('stripe_id', $data['id'])->first();

        if (!$subscription) {
            return;
        }

        $subscription->update([
            'stripe_status' => 'ACTIVE',
            'ends_at' => Carbon::now()->addMonth(), // Clear the end date when resumed
        ]);

    }

    /**
     * Handle subscription expired event
     */
    protected function handleSubscriptionExpired($payload)
    {
        $data = $payload['data'];
        $subscription = Subscription::where('stripe_id', $data['id'])->first();

        if (!$subscription) {
            return;
        }

        $subscription->update([
            'stripe_status' => 'EXPIRED',
            'ends_at' => $data['attributes']['ends_at'] ? 
                Carbon::parse($data['attributes']['ends_at']) : Carbon::now(),
        ]);
    }

    /**
     * Handle subscription paused event
     */
    protected function handleSubscriptionPaused($payload)
    {
        $data = $payload['data'];
        $subscription = Subscription::where('stripe_id', $data['id'])->first();

        if (!$subscription) {
            return;
        }

        $subscription->update([
            'stripe_status' => 'PAUSED',
        ]);
    }

    /**
     * Handle subscription unpaused event
     */
    protected function handleSubscriptionUnpaused($payload)
    {
        $data = $payload['data'];
        $subscription = Subscription::where('stripe_id', $data['id'])->first();

        if (!$subscription) {
          
            return;
        }

        $subscription->update([
            'stripe_status' => 'ACTIVE',
        ]);

    }

    /**
     * Map LemonSqueezy status to your local status format
     */
    protected function mapLemonSqueezyStatus($lemonSqueezyStatus)
    {
        $statusMap = [
            'on_trial' => 'TRIAL',
            'active' => 'ACTIVE',
            'paused' => 'PAUSED',
            'past_due' => 'PAST_DUE',
            'unpaid' => 'UNPAID',
            'cancelled' => 'CANCELED',
            'expired' => 'EXPIRED',
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