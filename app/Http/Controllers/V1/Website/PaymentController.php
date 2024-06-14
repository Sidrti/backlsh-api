<?php

namespace App\Http\Controllers\V1\Website;

use App\Helpers\Helper;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Subscriptions\PayPalSubscriptions;
use Carbon\Carbon;
use Illuminate\Support\Facades\Response;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Laravel\Cashier\Subscription;
use League\Csv\Reader;
use Stripe\Price;
use Stripe\Stripe;
use Stripe\Subscription as StripeSubscription;
use Srmklive\PayPal\Services\PayPal as PayPalClient;

class PaymentController extends Controller
{
    public function createCheckout(Request $request)
    {
        $paypalUrl = $this->paypalSubscription(1,'siddhant.singh326@gmail.com','Siddhant');
        return redirect()->away($paypalUrl);

        // $stripe =  $request->user()
        // ->newSubscription('default', 'price_1OgjuEH8GTBeCWthkvUf6ox8')
        // ->allowPromotionCodes()
        // ->checkout([
        //     'success_url' => config('app.success_url'),
        //     'cancel_url' => config('app.cancel_url'),
        // ]);
        // $data = [
        //     'status_code' => 1,
        //     'data' => [
        //         'url' => $stripe->url,
        //     ],

        // ];
        // return response()->json($data);
        // return redirect()->away($stripe->url);
    }
    public function paypalSubscription($qty = 0,$email,$username)
    {
        $provider = new PayPalClient;
        $provider->setApiCredentials(config('paypal'));
        $provider->getAccessToken();

        $productData = [
            'name' => 'Backlsh Subscription',
            'description' => 'Backlsh Subscription',
            'type' => 'SERVICE',
            'category' => 'SOFTWARE'
        ];
        $productResponse = $provider->createProduct($productData);
        if (!isset($productResponse['id'])) {
        }
        $productId = $productResponse['id'];

        $planData = [
            'product_id' => $productId,
            'name' => 'Demo Plan',
            'description' => 'Demo Plan',
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
                            'value' => '100',
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
            ]
        ];

        $planResponse = $provider->createPlan($planData);
        $planId = $planResponse['id'];
     
        $data = [
            'plan_id' => $planId, 
            'quantity' => $qty,
            'shipping_amount' => [
                'currency_code' => 'USD',
                'value' => 0.00,
            ],
            'subscriber' => [
                'name' => [
                    'given_name' => $username,
                    'surname' => '',
                ],
                'email_address' => $email,
                'shipping_address' => [
                    'name' => [
                        'full_name' =>$username,
                    ],
                    'address' => [
                        'address_line_1' => '2211 N First Street',
                        'address_line_2' => 'Building 17',
                        'admin_area_2' => 'San Jose',
                        'admin_area_1' => 'CA',
                        'postal_code' => '95131',
                        'country_code' => 'US',
                    ],
                ],
            ],
            'application_context' => [
                'brand_name' => 'Backlsh',
                'locale' => 'en-US',
                'shipping_preference' => 'SET_PROVIDED_ADDRESS',
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

        $subscriptionResponse = $provider->createSubscription($data);
       // dd($subscriptionResponse);

        if (isset($subscriptionResponse['id']) && $subscriptionResponse['id'] != null) {

            foreach ($subscriptionResponse['links'] as $link) {
                if ($link['rel'] == 'approve') {
                    //return redirect()->away($link['href']);
                    return $link['href'];
                }
            }
        }
    }
    public function fetchBillingDetails(Request $request)
    {
        $subscription = (Helper::getUserSubscription(auth()->user()->id));
        $data = [
            'status_code' => 1,
            'data' => [
                'billing' => $subscription,
            ],
            "message" => 'User details fetched',

        ];
        return response()->json($data);
    }
    public function rediectToBilling(Request $request)
    {
        //return auth()->user()->redirectToBillingPortal('http://127.0.0.1:8000');
    }
    public function handleWebhook(Request $request)
    {
        // Log the webhook payload

        // Get the event type
        $eventType = $request->input('event_type');

        // Handle different event types
        switch ($eventType) {
            // case 'BILLING.SUBSCRIPTION.ACTIVATED':
            //     $this->handleSubscriptionCreated($request->all());
            //     break;

            case 'BILLING.SUBSCRIPTION.CANCELLED':
                $this->handleSubscriptionCancelled($request->all());
                break;

            case 'PAYMENT.SALE.COMPLETED':
                $this->handlePaymentCompleted($request->all());
                break;

            // Add more cases as needed

            default:
               // Log::warning('Unhandled PayPal Webhook Event:', ['event_type' => $eventType]);
                break;
        }

        // Respond with a 200 status to acknowledge receipt
        return response()->json(['status' => 'success'], 200);
    }

    // protected function handleSubscriptionCreated($payload)
    // {
    //     // Implement your logic to handle a subscription creation
    //     Log::info('Subscription Created:', $payload);
    // }
    protected function handleSubscriptionCancelled($payload)
    {
        // Implement your logic to handle a subscription cancellation
        Log::info('Subscription Cancelled:', $payload);
    }
    protected function handlePaymentCompleted($payload)
    {
        // Implement your logic to handle a payment completion
        Log::info('Payment Completed:', $payload);
    }
}
