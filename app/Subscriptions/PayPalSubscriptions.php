<?php

namespace App\Subscriptions;

use Exception;
use Illuminate\Support\Facades\Log;
use Srmklive\PayPal\Services\PayPal as PayPalClient;


class PayPalSubscriptions
{
    protected $provider;

    public function __construct()
    {
        $this->provider = new PayPalClient;
        $this->provider->setApiCredentials(config('paypal'));
    }
    public function create(int $plan_id, int $coupon_user_id, string $method, float $amount = 0)
    {
        $this->provider->getAccessToken();
        // dd($this->provider);
        $response = $this->provider->addProduct('Demo Product', 'Demo Product', 'SERVICE', 'SOFTWARE')
        ->addMonthlyPlan('Demo Plan', 'Demo Plan', 100)
        ->setReturnAndCancelUrl('https://api.backlsh.com/public/payment/success', 'https://api.backlsh.com/public/payment/cancel')
        ->setupSubscription('Siddhant', 'siddhant.singh326@gmail.com', '2024-06-12');


        dd($response);
    }

    // Methods for creating, canceling, pausing, resuming, and updating subscriptions
    // will be implemented here
}
