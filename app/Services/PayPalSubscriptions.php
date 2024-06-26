<?php

namespace App\Services;

use Exception;
use Illuminate\Support\Facades\Log;
use Laravel\Cashier\Subscription;
use Srmklive\PayPal\Services\PayPal as PayPalClient;


class PayPalSubscriptions
{
    protected $provider;

    public function __construct()
    {
        $this->provider = new PayPalClient;
        $this->provider->setApiCredentials(config('paypal'));
        $this->provider->getAccessToken();
    }

    public function createCheckout($user,$qty)
    {
        $productId = $this->createProduct();
        $planId = $this->createPlan($productId);
        $data = [
            'plan_id' => $planId,
            'quantity' => $qty,
            'shipping_amount' => [
                'currency_code' => 'USD',
                'value' => 0.00,
            ],
            'subscriber' => [
                'name' => [
                    'given_name' => $user->name,
                    'surname' => '',
                ],
                'email_address' => $user->email,
            ],
            'application_context' => [
                'brand_name' => 'Backlsh',
                'locale' => 'en-US',
                'shipping_preference' => 'NO_SHIPPING',
                'user_action' => 'SUBSCRIBE_NOW',
                'payment_method' => [
                    'payer_selected' => 'PAYPAL',
                    'payee_preferred' => 'IMMEDIATE_PAYMENT_REQUIRED',
                ],
                'return_url' => config('app.success_url'),
                'cancel_url' => config('app.cancel_url'),
                'landing_page' => 'BILLING' // Add this line
            ],
        ];
        $subscriptionResponse = $this->provider->createSubscription($data);

        if (isset($subscriptionResponse['id'])) {
    
            $subscription = Subscription::create([
                'user_id' => $user->id,
                'name' => 'Premium Plan',
                'stripe_id' => $subscriptionResponse['id'], // PayPal subscription ID
                'stripe_status' => 'PENDING',
                'quantity' => $qty,
            ]);
        }
        if (isset($subscriptionResponse['id']) && $subscriptionResponse['id'] != null) {
            foreach ($subscriptionResponse['links'] as $link) {
                if ($link['rel'] == 'approve') {
                    return $link['href'];
                }
            }
        }
        return -1;
    }
    public function updateQuantity($subscriptionId,$qty)
    {
        $patchData = [
            "quantity"=> $qty
        ];
        dd($this->provider->reviseSubscription($subscriptionId, $patchData));
        return true;
    }
    public function createProduct()
    {
        $productData = [
            'name' => 'Backlsh Subscription',
            'description' => 'Backlsh Subscription',
            'type' => 'SERVICE',
            'category' => 'SOFTWARE'
        ];
        $productResponse = $this->provider->createProduct($productData);
        if (isset($productResponse['id'])) {
            $productId = $productResponse['id'];
            return $productId;
        }
        return null;
    }
    public function createPlan($productId)
    {
        $planData = [
            'product_id' => $productId,
            'name' => 'Backlsh Premium',
            'description' => 'Just $2.99/month/member to jump productivity heights by 70%',
            'billing_cycles' => [
                [
                    'frequency' => [
                        'interval_unit' => 'MONTH',
                        'interval_count' => 1,
                    ],
                    'tenure_type' => 'REGULAR',
                    'sequence' => 1,
                    'total_cycles' => 12,
                    'pricing_scheme' => [
                        'fixed_price' => [
                            'value' => '2.99',
                            'currency_code' => 'USD'
                        ]
                    ]
                ]
            ],
            'payment_preferences' => [
                'auto_bill_outstanding' => true,
                'setup_fee' => [
                    'value' => '0',
                    'currency_code' => 'USD'
                ],
                'setup_fee_failure_action' => 'CONTINUE',
                'payment_failure_threshold' => 3
            ],
            'quantity_supported' => true
        ];

        $planResponse = $this->provider->createPlan($planData);
        $planId = $planResponse['id'];
        return $planId;
    }
}
