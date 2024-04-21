<?php

namespace App\Http\Controllers\V1\Website;

use App\Helpers\Helper;
use App\Http\Controllers\Controller;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Response;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Laravel\Cashier\Subscription;
use League\Csv\Reader;
use Stripe\Price;
use Stripe\Stripe;
use Stripe\Subscription as StripeSubscription;

class PaymentController extends Controller
{
    public function createCheckout(Request $request)
    {
        $stripe =  $request->user()
            ->newSubscription('default', 'price_1OgjuEH8GTBeCWthkvUf6ox8')
            ->allowPromotionCodes()
            ->checkout([
                'success_url' => config('app.success_url'),
                'cancel_url' => config('app.cancel_url'),
            ]);
            $data = [
                'status_code' => 1,
                'data' => [
                    'url' => $stripe->url,
                ],
    
            ];
            return response()->json($data);
           // return redirect()->away($stripe->url);
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
}