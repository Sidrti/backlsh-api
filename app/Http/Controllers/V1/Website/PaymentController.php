<?php

namespace App\Http\Controllers\V1\Website;

use App\Helpers\Helper;
use App\Http\Controllers\Controller;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Response;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use League\Csv\Reader;

class PaymentController extends Controller
{
    public function createCheckout(Request $request)
    {
        $stripe =  $request->user()
            ->newSubscription('default', 'price_1P2AD6SIjbH04JRm6Q3SbSqH')
            ->allowPromotionCodes()
            ->checkout([
                'success_url' => config('app.success_url'),
                'cancel_url' => config('app.cancel_url'),
            ]);
            return response($stripe->url);
            return redirect()->away($stripe->url);
    }
}