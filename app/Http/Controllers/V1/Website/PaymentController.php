<?php

namespace App\Http\Controllers\V1\Website;

use App\Helpers\Helper;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\PayPalSubscriptions;
use Carbon\Carbon;
use Exception;
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
    protected $paypalSubscriptionService;

    public function __construct(PayPalSubscriptions $paypalSubscriptionService)
    {
        $this->paypalSubscriptionService = $paypalSubscriptionService;
    }
    public function createCheckout(Request $request)
    {
        $user = auth()->user();
        // $teamMembersCount = User::where(function ($query) use ($user) {
        //     $query->where('users.parent_user_id', $user->id)
        //           ->orWhere('users.id', $user->id);
        //     })
        // ->count();
        $teamMembersCount = 1;
      
        $paypalUrl = $this->paypalSubscriptionService->createCheckout($user,$teamMembersCount);   
        //  $paypalUrl = $this->paypalSubscription($teamMembersCount, $user);
        //return $paypalUrl;
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
    // public function paypalSubscription($qty = 1, $user)
    // {
    //     $provider = new PayPalClient;
    //     $provider->setApiCredentials(config('paypal'));
    //     $provider->getAccessToken();

    //     $productData = [
    //         'name' => 'Backlsh Subscription',
    //         'description' => 'Backlsh Subscription',
    //         'type' => 'SERVICE',
    //         'category' => 'SOFTWARE'
    //     ];
    //     $productResponse = $provider->createProduct($productData);
    //     if (!isset($productResponse['id'])) {
    //     }
    //     $productId = $productResponse['id'];

    //     $planData = [
    //         'product_id' => $productId,
    //         'name' => 'Backlsh Premium',
    //         'description' => 'Just $2.99/month/member to jump productivity heights by 70%',
    //         'billing_cycles' => [
    //             [
    //                 'frequency' => [
    //                     'interval_unit' => 'MONTH',
    //                     'interval_count' => 1,
    //                 ],
    //                 'tenure_type' => 'REGULAR',
    //                 'sequence' => 1,
    //                 'total_cycles' => 12,
    //                 'pricing_scheme' => [
    //                     'fixed_price' => [
    //                         'value' => '2.99',
    //                         'currency_code' => 'USD'
    //                     ]
    //                 ]
    //             ]
    //         ],
    //         'payment_preferences' => [
    //             'auto_bill_outstanding' => true,
    //             'setup_fee' => [
    //                 'value' => '0',
    //                 'currency_code' => 'USD'
    //             ],
    //             'setup_fee_failure_action' => 'CONTINUE',
    //             'payment_failure_threshold' => 3
    //         ],
    //         'quantity_supported' => true
    //     ];

    //     $planResponse = $provider->createPlan($planData);
    //     $planId = $planResponse['id'];
    //     $data = [
    //         'plan_id' => $planId,
    //         'quantity' => $qty,
    //         'shipping_amount' => [
    //             'currency_code' => 'USD',
    //             'value' => 0.00,
    //         ],
    //         'subscriber' => [
    //             'name' => [
    //                 'given_name' => $user->name,
    //                 'surname' => '',
    //             ],
    //             'email_address' => $user->email,
    //             'shipping_address' => [
    //                 'name' => [
    //                     'full_name' => $user->name,
    //                 ],
    //                 'address' => [
    //                     'address_line_1' => '2211 N First Street',
    //                     'address_line_2' => 'Building 17',
    //                     'admin_area_2' => 'San Jose',
    //                     'admin_area_1' => 'CA',
    //                     'postal_code' => '95131',
    //                     'country_code' => 'US',
    //                 ],
    //             ],
    //         ],
    //         'application_context' => [
    //             'brand_name' => 'Backlsh',
    //             'locale' => 'en-US',
    //             'shipping_preference' => 'SET_PROVIDED_ADDRESS',
    //             'user_action' => 'SUBSCRIBE_NOW',
    //             'payment_method' => [
    //                 'payer_selected' => 'PAYPAL',
    //                 'payee_preferred' => 'IMMEDIATE_PAYMENT_REQUIRED',
    //             ],
    //             'return_url' => config('app.success_url'),
    //             'cancel_url' => config('app.cancel_url'),
    //             'landing_page' => 'BILLING' // Add this line
    //         ],
    //     ];

    //     $subscriptionResponse = $provider->createSubscription($data);

    //     if (isset($subscriptionResponse['id'])) {
    
    //         $subscription = Subscription::create([
    //             'user_id' => $user->id,
    //             'name' => 'Premium Plan',
    //             'stripe_id' => $subscriptionResponse['id'], // PayPal subscription ID
    //             'stripe_status' => 'PENDING',
    //             'quantity' => $qty,
    //         ]);
    //     }
    //     if (isset($subscriptionResponse['id']) && $subscriptionResponse['id'] != null) {
    //         foreach ($subscriptionResponse['links'] as $link) {
    //             if ($link['rel'] == 'approve') {
    //                 return $link['href'];
    //             }
    //         }
    //     }
    //     return -1;
    // }
    // public function cancelSubscription($subscriptionId)
    // {
    //     try {
    //         $response = $this->provider->cancelSubscription($subscriptionId);

    //         if ($response['status'] === 'CANCELLED') {
    //             Subscription::where('stripe_id', $subscriptionId)->update(['stripe_status' => 'CANCELLED']);
    //         }

    //         return $response;
    //     } catch (Exception $e) {
    //         return ['error' => $e->getMessage()];
    //     }
    // }
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
        $eventType = $request->input('event_type');

        switch ($eventType) {
                // case 'BILLING.SUBSCRIPTION.ACTIVATED':
                //     $this->handleSubscriptionCreated($request->all());
                //     break;

            case 'BILLING.SUBSCRIPTION.CANCELLED':
                $this->handleSubscriptionCancelled($request->all());
                break;

            case 'BILLING.SUBSCRIPTION.ACTIVATED':
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
    public function paymentSuccess(Request $request)
    {
        $id = $request->input('subscription_id');
        $date = Carbon::today()->format('Y-m-d');
        $subscription = Subscription::where('stripe_id',$id)->first();
        $qty = $subscription->quantity;
        $price = config('app.unit_price') * $qty;
        $redirectUrl = config('app.website_url').'/payment-success?member='.$qty.'&amount='.$price.'&date='.$date.'&currency=USD&id='.$id;
        return redirect()->away($redirectUrl);
    }
    public function paymentCancel(Request $request)
    {
        $redirectUrl = config('app.website_url').'/billing?error=true';
        return redirect()->away($redirectUrl);
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
        $subscriptionId = $payload['resource']['id'];
        $subscription = Subscription::where('stripe_id', $subscriptionId)->update(['stripe_status' => 'CANCELLED']);
        $data = [
            'status_code' => 1,
            'data' => [
                'subscription' => $subscription,
            ],
            "message" => 'Subscription Cancelled',

        ];
        return response()->json($data);
    }
    protected function handlePaymentCompleted($payload)
    {
        // Implement your logic to handle a payment completion
        Log::info('Payment Completed:', $payload);
        $subscriptionId = $payload['resource']['id'];
        $nextPaymentDate = $payload['resource']['billing_info']['next_billing_time'];
        $nextPaymentDate = Carbon::parse($nextPaymentDate)->format('Y-m-d H:i:s');
        $subscription = Subscription::where('stripe_id', $subscriptionId)->update(['stripe_status' => 'ACTIVE','ends_at' => $nextPaymentDate]);
        dd($subscription);
        $data = [
            'status_code' => 1,
            'data' => [
                'subscription' => $subscription,
            ],
            "message" => 'Subscription Activated',

        ];
        return response()->json($data);
    }
}
