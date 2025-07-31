<?php

namespace App\Helpers;

use App\Models\AttendanceSchedule;
use App\Models\Process;
use App\Models\User;
use App\Models\UserActivity;
use App\Models\UserProcessRating;
use App\Services\PayPalSubscriptions;
use Carbon\Carbon;
use Exception;
use File;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use ImageKit\ImageKit;
use Srmklive\PayPal\Services\PayPal as PayPalClient;
use Stripe\Price;
use Stripe\Stripe;
use Stripe\Subscription;

class Helper
{
    public static function computeType($process)
    {

        if ($process == 'firefox' || $process == 'chrome' || $process == 'edge' || $process == 'msedge' || $process == 'safari') {
            return 'BROWSER';
        } else {
            return 'APPLICATION';
        }
    }
    public static function computeActivityProductivityStatus($process, $userId)
    {
        $adminId = User::where('id', $userId)->value('parent_user_id');
        if ($adminId == 0) {
            $adminId = $userId;
        }

        $userProcessRatings = UserProcessRating::join('processes', 'processes.id', 'user_process_ratings.process_id')
            ->where('user_process_ratings.user_id', $adminId)
            ->where('processes.process_name', $process)
            ->first();

        return isset($userProcessRatings->rating) ? $userProcessRatings->rating : 'NEUTRAL';
    }
    public static function computeSubActivityProductivityStatus($url)
    {
        return 'NONPRODUCTIVE';
    }
    public static function saveImageToServer($file, $dir, $saveLocal = false)
    {
        $filename = rand(10000, 100000) . '_' . time() . '_' . $file->getClientOriginalName();
        if ($saveLocal) {
            $filePath = $file->storeAs($dir, $filename, 'public'); // 'public' disk to store in 'storage/app/public'
            return $filePath;
        } else {
            $filename = rand(10000, 100000) . '_' . time() . '.' . $file->getClientOriginalExtension();
            $imageKit = new ImageKit(
                config('app.image_kit_public_key'),
                config('app.image_kit_private_key'),
                config('app.image_kit_url')
            );
            $response = $imageKit->uploadFile([
                "file" => fopen($file->getPathname(), "r"), // Open the file stream
                "fileName" => $filename, // Use the generated filename
                "folder" => "/screenshots/", // Optional: specify a folder in ImageKit
                "useUniqueFileName" => true // Let ImageKit handle unique filenames
            ]);

            // Check if the upload was successful
            if ($response->error === null) {
                // Get the URL of the uploaded image
                $fileUrl = $response->result->url;
                return $fileUrl;
            } else {
                return -1;
            }
        }
    }
    public static function sendEmail($to, $subject, $body, $name = "Max")
    {
        $headers = "MIME-Version: 1.0" . "\r\n";
        $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
        // mail($to, $subject, $body,$headers);

        $credentials = \SendinBlue\Client\Configuration::getDefaultConfiguration()->setApiKey('api-key', config('app.sendinblue_key'));
        $apiInstance = new \SendinBlue\Client\Api\TransactionalEmailsApi(new \GuzzleHttp\Client(), $credentials);

        $sendSmtpEmail = new \SendinBlue\Client\Model\SendSmtpEmail([
            'subject' => $subject,
            'sender' => ['name' => 'Jacquie', 'email' => 'hi@backlsh.in'],
            'replyTo' => ['name' => 'Support', 'email' => 'support@backlsh.com'],
            'to' => [['name' => $name, 'email' => $to]],
            'htmlContent' => $body,
        ]);

        try {
            $result = $apiInstance->sendTransacEmail($sendSmtpEmail);
            return true;
        } catch (Exception $e) {
            return $e->getMessage();
            echo $e->getMessage(), PHP_EOL;
        }
    }
    public static function calculateTotalHoursByUserId($userId, $startDate, $endDate, $status = null, $convertFormatToReadableFormat = true)
    {
        $userActivities = UserActivity::where('user_id', $userId)
            ->join('processes', 'processes.id', 'user_activities.process_id')
            // ->whereBetween('start_datetime', [$startDate, $endDate])
            // ->whereBetween('end_datetime', [$startDate, $endDate])
            ->whereRaw("DATE(user_activities.start_datetime) BETWEEN ? AND ?", [$startDate, $endDate])
            ->whereRaw("DATE(user_activities.start_datetime) BETWEEN ? AND ?", [$startDate, $endDate])
            ->whereRaw("DATE(user_activities.start_datetime) = DATE(user_activities.end_datetime)")
            ->where('processes.process_name', '!=', '-1')
            ->where('processes.process_name', '!=', 'LockApp')
            ->where('processes.process_name', '!=', 'Idle')
            ->get();

        $filteredActivities = $status ? $userActivities->where('productivity_status', $status) : $userActivities;

        $totalSeconds = $filteredActivities->sum(function ($activity) {
            return Carbon::parse($activity->end_datetime)->diffInSeconds(Carbon::parse($activity->start_datetime));
        });
        return $convertFormatToReadableFormat ? Helper::convertSecondsInReadableFormat($totalSeconds) : $totalSeconds;
    }
    public static function calculateTotalHoursByParentId($userId, $startDate, $endDate, $status = null)
    {
        $userActivities = UserActivity::join('users', 'users.id', '=', 'user_activities.user_id')
            ->where(function ($query) use ($userId) {
                $query->where('users.parent_user_id', $userId)
                    ->orWhere('users.id', $userId);
            })
            ->join('processes', 'processes.id', 'user_activities.process_id')
            ->whereRaw("DATE(user_activities.start_datetime) BETWEEN ? AND ?", [$startDate, $endDate])
            ->where('processes.process_name', '!=', '-1')
            ->where('processes.process_name', '!=', 'LockApp')
            ->where('processes.process_name', '!=', 'Idle')
            ->whereRaw("DATE(user_activities.end_datetime) BETWEEN ? AND ?", [$startDate, $endDate])
            ->whereRaw("DATE(user_activities.start_datetime) = DATE(user_activities.end_datetime)")
            ->get();

        $filteredActivities = $status ? $userActivities->where('productivity_status', $status) : $userActivities;

        // return round($filteredActivities->sum(function ($activity) {
        //     return Carbon::parse($activity->end_datetime)->diffInSeconds(Carbon::parse($activity->start_datetime)) / 3600;
        // }), 1);

        $totalSeconds = $filteredActivities->sum(function ($activity) {
            return Carbon::parse($activity->end_datetime)->diffInSeconds(Carbon::parse($activity->start_datetime));
        });
        return Helper::convertSecondsInReadableFormat($totalSeconds);
    }
    public static function getUserAttendance($userId, $startDate, $endDate)
    {
        $daysPresent = 0;
        $totalDays = 0;
        // Iterate over each day of the month
        for ($date = $startDate; $date->lte($endDate); $date->addDay()) {
            // Get the user activities for the current day
            $userActivities = UserActivity::where('user_id', $userId)
                ->whereDate('start_datetime', $date->toDateString())
                ->get();

            $schedule = AttendanceSchedule::where('start_date', '<=', $date)
                ->where('end_date', '>=', $date)
                ->whereHas('users', function ($query) use ($userId) {
                    $query->where('user_id', $userId);
                })
                ->first();

            // Calculate the total working hours for the day
            $totalHours = 0;
            foreach ($userActivities as $activity) {
                $hours = Carbon::parse($activity->end_datetime)->diffInSeconds(Carbon::parse($activity->start_datetime)) / 3600;
                $totalHours += $hours;
            }

            // If the total working hours for the day exceed 4, increment the days present counter
            if ($schedule) {
                ($totalHours >= $schedule->min_hours) ? $daysPresent++ : (($totalHours > 0) ? $daysPresent = $daysPresent + 0.5 : $daysPresent);
            } else {
                if ($totalHours > 1) {
                    $daysPresent++;
                }
            }


            $totalDays++;
        }

        return ['days_present' => $daysPresent, 'total_days' => $totalDays];
    }
    public static function getDomainFromUrl($url)
    {
        if (!preg_match('/^https?:\/\//', $url)) {
            $url = 'http://' . $url;
        }
        $parsedUrl = parse_url($url);
        $domain = isset($parsedUrl['host']) ? $parsedUrl['host'] : '';
        if (substr($domain, 0, 4) == 'www.') {
            $domain = substr($domain, 4);
        }

        return $domain;
    }
    public static function getMembersOnlineCount($userId)
    {
        $thresholdTime = Carbon::now()->subMinutes(10);
        $today = Carbon::today();

        $onlineMembersCount = UserActivity::whereDate('user_activities.start_datetime', $today)
            ->join('users', 'users.id', 'user_activities.user_id')
            ->where('start_datetime', '>=', $thresholdTime)
            ->where(function ($query) use ($userId) {
                $query->where('users.parent_user_id', $userId)
                    ->orWhere('users.id', $userId);
            })
            ->distinct('user_activities.user_id')
            ->count();

        return $onlineMembersCount;
    }
    public static function createNewUser($name, $email, $password, $loginType, $role, $parentId = 0)  //loginType - 1 (Normal),  2 - Google,  3 - linkedin
    {
        $randomSeed = uniqid();
        $style = 'avataaars'; // You can change style

        $avatarUrl = "https://api.dicebear.com/7.x/$style/svg?seed=$randomSeed";

        $user = User::create([
            'name' => $name,
            'email' => $email,
            'password' => bcrypt($password),
            'login_type' => $loginType,
            'role' => $role,
            'is_verified' => $loginType == 2 || $loginType == 3 ? 1 : 0,
            'parent_user_id' => $parentId,
            'trial_ends_at' => now()->addDays(10),
            'profile_picture' => $avatarUrl,
        ]);

        return $user;
    }
    public static function getUserSubscription($userId)
    {
        Stripe::setApiKey(config('app.stripe_key'));
        $user = User::find($userId);
        $teamMemberCount = User::where('parent_user_id', $user->id)->count() + 1;
        if ($user->isPayPalSubscribed()) {
            $subscription = $user->subscriptions()->first();
            $paypalSubscriptionId = $subscription->stripe_id;
            $subscriptionData = Helper::getPayPalSubscription($paypalSubscriptionId);

            if ($subscriptionData) {
                $priceAmount = $subscriptionData['billing_info']['last_payment']['amount']['value'];
                $currency = $subscriptionData['billing_info']['last_payment']['amount']['currency_code'];
                $current_period_end = Carbon::parse($subscriptionData['billing_info']['next_billing_time']);
                $current_period_start = Carbon::parse($subscriptionData['create_time']);
                $totalAmount = $priceAmount * $teamMemberCount;
            }
        } else {
            $priceAmount = config('app.unit_price');
            $totalAmount = config('app.unit_price') * $teamMemberCount;
            $currency = '$';
            $current_period_end = $user->created_at;
            $current_period_start = $user->trial_ends_at;
        }



        $remainingTrialDays = 0;
        if ($user->onTrial()) {
            $currentDate = Carbon::now();

            $remainingTrialDays = $currentDate->diffInDays($user->trial_ends_at);
        }
        return [
            'trial' => $user->onTrial(),
            'subscribed' => $user->isPayPalSubscribed(),
            'price' => $priceAmount,
            'total_price' => $totalAmount,
            'currency' => $currency,
            'current_period_end' => $current_period_end,
            'current_period_start' => $current_period_start,
            'member_count' => $teamMemberCount,
            'remaining_trial_days' => $remainingTrialDays
        ];
    }
    protected static function getPayPalSubscription($subscriptionId)
    {
        try {
            $paypal = new PayPalClient;
            $paypal->setApiCredentials(config('paypal'));
            $paypal->getAccessToken();
            $response = $paypal->showSubscriptionDetails($subscriptionId);

            if (isset($response['id']) && $response['id'] == $subscriptionId) {
                return $response;
            } else {
                return null;
            }
        } catch (Exception $e) {
            Log::error('Error fetching PayPal subscription: ' . $e->getMessage());
            return null;
        }
    }
    public static function convertSecondsInReadableFormat($totalSeconds)
    {
        $hours = floor($totalSeconds / 3600);
        $minutes = floor(($totalSeconds % 3600) / 60);

        $formattedTime = '';
        if ($hours > 0) {
            $formattedTime .= "{$hours}h ";
        }
        if ($minutes > 0) {
            $formattedTime .= "{$minutes}m";
        }

        // If there are no hours or minutes, display "0m"
        if (empty($formattedTime)) {
            $formattedTime = '0m';
        }

        return $formattedTime;
    }
    public static function getWeeklyProductivityReport($userId)
    {
        $teamUserIds = User::where('parent_user_id', $userId)
                        ->orWhere('id', $userId)
                        ->pluck('id');
        // Get the start and end of the current week (Monday to Sunday)
        $startOfWeek = Carbon::now()->startOfWeek(Carbon::MONDAY);
        $endOfWeek = Carbon::now()->endOfWeek(Carbon::SUNDAY);
        // Get productivity summaries for the week
        $summaries = DB::table('user_productivity_summaries')
            ->whereIn('user_id', $teamUserIds)
            ->whereBetween('date', [$startOfWeek->format('Y-m-d'), $endOfWeek->format('Y-m-d')])
            ->select(
                'date',
                DB::raw('SUM(productive_seconds) as productive_seconds'),
                DB::raw('SUM(nonproductive_seconds) as nonproductive_seconds'),
                DB::raw('SUM(neutral_seconds) as neutral_seconds'),
                DB::raw('SUM(total_seconds) as total_seconds')
            )
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        $weeklyReport = [];

        foreach ($summaries as $summary) {
            $date = Carbon::parse($summary->date);
            $productivePercent = 0;

            // Calculate productivity percentage (only if there's actual tracked time)
            if ($summary->total_seconds > 0) {
                $productivePercent = round(($summary->productive_seconds / $summary->total_seconds) * 100);
            }

            $weeklyReport[] = [
                'day' => $date->format('D'), // Mon, Tue, etc.
                'date' => $date->format('d-m-Y'),
                'productivity_percent' => $productivePercent,
                'productive_time' => Helper::convertSecondsInReadableFormat($summary->productive_seconds),
                'nonproductive_time' => Helper::convertSecondsInReadableFormat($summary->nonproductive_seconds),
                'neutral_time' => Helper::convertSecondsInReadableFormat($summary->neutral_seconds),
                'total_time' => Helper::convertSecondsInReadableFormat($summary->total_seconds)
            ];
        }

        // Fill in missing days with zero values
        $completeReport = [];
        $currentDate = $startOfWeek->copy();

        while ($currentDate <= $endOfWeek) {
            $dateStr = $currentDate->format('Y-m-d');
            $found = false;

            foreach ($weeklyReport as $dayReport) {
                if (Carbon::parse($dayReport['date'])->format('Y-m-d') === $dateStr) {
                    $completeReport[] = $dayReport;
                    $found = true;
                    break;
                }
            }

            if (!$found) {
                $completeReport[] = [
                    'day' => $currentDate->format('D'),
                    'date' => $currentDate->format('d-m-Y'),
                    'productivity_percent' => 0,
                    'productive_time' => '0m',
                    'nonproductive_time' => '0m',
                    'neutral_time' => '0m',
                    'total_time' => '0m'
                ];
            }

            $currentDate->addDay();
        }

        return $completeReport;
    }
}
