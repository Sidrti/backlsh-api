<?php

namespace App\Helpers;

use App\Models\AttendanceSchedule;
use App\Models\Process;
use App\Models\User;
use App\Models\UserActivity;
use App\Models\UserProcessRating;
use App\Models\UserProductivitySummary;
use App\Models\UserSubActivity;
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
            return $filePath; // Return relative path, let the model accessor handle URL construction
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
    public static function calculateTotalHoursByParentId($teamUserIds=[], $startDate, $endDate, $status = null, $convertFormatToReadableFormat = true)
    {
        $status = strtoupper((string) $status);

        if ($status === 'PRODUCTIVE') {
            $column = 'productive_seconds';
        } elseif ($status === 'NONPRODUCTIVE') {
            $column = 'nonproductive_seconds';
        } elseif ($status === 'NEUTRAL') {
            $column = 'neutral_seconds';
        } else {
            $column = 'total_seconds';
        }
        $totalSeconds = UserProductivitySummary::whereBetween('date', [$startDate, $endDate])
            ->wherein('user_id',$teamUserIds)
            ->sum($column);

        return $convertFormatToReadableFormat ? Helper::convertSecondsInReadableFormat($totalSeconds) : $totalSeconds;
    }
    // public static function calculateTotalHoursByParentId($userId, $startDate, $endDate, $status = null, $convertFormatToReadableFormat = true)
    // {
    //     $userActivities = UserActivity::join('users', 'users.id', '=', 'user_activities.user_id')
    //         ->where(function ($query) use ($userId) {
    //             $query->where('users.parent_user_id', $userId)
    //                 ->orWhere('users.id', $userId);
    //         })
    //         ->join('processes', 'processes.id', 'user_activities.process_id')
    //         ->whereRaw("DATE(user_activities.start_datetime) BETWEEN ? AND ?", [$startDate, $endDate])
    //         ->where('processes.process_name', '!=', '-1')
    //         ->where('processes.process_name', '!=', 'LockApp')
    //         ->where('processes.process_name', '!=', 'Idle')
    //         ->whereRaw("DATE(user_activities.end_datetime) BETWEEN ? AND ?", [$startDate, $endDate])
    //         ->whereRaw("DATE(user_activities.start_datetime) = DATE(user_activities.end_datetime)")
    //         ->get();

    //     $filteredActivities = $status ? $userActivities->where('productivity_status', $status) : $userActivities;

    //     // return round($filteredActivities->sum(function ($activity) {
    //     //     return Carbon::parse($activity->end_datetime)->diffInSeconds(Carbon::parse($activity->start_datetime)) / 3600;
    //     // }), 1);

    //     $totalSeconds = $filteredActivities->sum(function ($activity) {
    //         return Carbon::parse($activity->end_datetime)->diffInSeconds(Carbon::parse($activity->start_datetime));
    //     });

    //     return $convertFormatToReadableFormat ? Helper::convertSecondsInReadableFormat($totalSeconds) : $totalSeconds;
    // }
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
    public static function getTopProcesses($days=7,$teamUserIds)
    {
      $today = Carbon::today();
      $startDate = $today->copy()->subDays($days - 1)->startOfDay()->format('Y-m-d H:i:s');
      $endDate   = $today->endOfDay()->format('Y-m-d H:i:s');


                 
        $totalTime = Helper::calculateTotalHoursByParentId($teamUserIds, $startDate, $endDate, null, false,false);
        // Main query
        $query = UserActivity::selectRaw(
            'processes.id,
             processes.process_name,
             processes.type,
              processes.icon as icon_url,
             SUM(TIMESTAMPDIFF(SECOND, user_activities.start_datetime, user_activities.end_datetime)) as total_seconds,
             user_activities.productivity_status'
        )
            ->join('processes', 'user_activities.process_id', '=', 'processes.id')
            ->whereBetween('start_datetime', [$startDate, $endDate])
            ->whereIn('user_activities.user_id', $teamUserIds)
            ->where('processes.process_name', '!=', '-1')
            ->where('processes.process_name', '!=', 'LockApp')
            ->where('processes.process_name', '!=', 'Idle')
            ->groupBy('processes.id', 'processes.process_name', 'processes.type', 'processes.icon', 'productivity_status')
            ->orderByDesc('total_seconds')
            ->limit(5)
            ->get()
            ->map(function ($process) use ($totalTime) {
                $process->icon_url = asset('storage/' . ($process->icon_url ??config(('app.process_default_image'))));
                $process->time_used_human = Helper::convertSecondsInReadableFormat($process->total_seconds);
                $process->percentage_time = $totalTime > 0 ? round(($process->total_seconds / $totalTime) * 100, 2) : 0;
                return $process;
            });

        return $query;
    }
    public static function getTopWebsites($days = 7,$teamUserIds)
    {
    $today = Carbon::today();
    $startDate = $today->copy()->subDays($days - 1)->startOfDay()->format('Y-m-d H:i:s');
    $endDate   = $today->endOfDay()->format('Y-m-d H:i:s');


        // Calculate total time for percentage calculation
        $totalTime = Helper::calculateTotalHoursByParentId($teamUserIds, $startDate, $endDate, null, false,false);

        // Main query
        $query = UserSubactivity::selectRaw(
                'SUM(TIMESTAMPDIFF(SECOND, user_sub_activities.start_datetime, user_sub_activities.end_datetime)) as total_seconds,
                user_sub_activities.productivity_status,
                processes.process_name,
                processes.icon,
                processes.type as process_type'
            )
            ->join('user_activities', 'user_sub_activities.user_activity_id', '=', 'user_activities.id')
            ->join('processes', 'user_sub_activities.process_id', '=', 'processes.id')
            ->whereIn('user_activities.user_id', $teamUserIds)
            ->whereBetween('user_sub_activities.start_datetime', [$startDate, $endDate])
            ->whereNotIn('processes.process_name', ['-1', 'LockApp', 'Idle'])
            ->groupBy('user_sub_activities.productivity_status', 'processes.process_name', 'processes.icon', 'processes.type')
            ->orderByDesc('total_seconds')
            ->limit(5)
            ->get()
            ->map(function ($website) use ($totalTime) {
                $website->icon_url = asset('storage/' . ($website->icon ?? config('app.process_default_image')));
                $website->time_used_human = Helper::convertSecondsInReadableFormat($website->total_seconds);
                $website->percentage_time = $totalTime > 0 ? round(($website->total_seconds / $totalTime) * 100, 2) : 0;
                return $website;
            });

        return $query;
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

        // $onlineMembersCount = UserProductivitySummary::where('date', $today->format('Y-m-d'))
        //     ->where('user_productivity_summaries.updated_at', '>=', $thresholdTime)
        //     ->join('users', 'users.id', 'user_productivity_summaries.user_id')
        //     ->where('user_productivity_summaries.total_seconds', '>', 0)
        //     ->where(function ($query) use ($userId) {
        //         $query->where('users.parent_user_id', $userId)
        //             ->orWhere('users.id', $userId);
        //     })
        //     ->distinct('user_productivity_summaries.user_id')
        //     ->count();

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
        $user = User::find($userId);
        $teamMemberCount = User::where('parent_user_id', $user->id)->count() + 1;
        if ($user->isPayPalSubscribed()) {
            $subscription = $user->subscriptions()->first();
            $paypalSubscriptionId = $subscription->stripe_id;
        
            if ($subscription) {
                $priceAmount = $subscription->stripe_price / config('app.unit_price'); 
                $currency = '$';
                $current_period_end = Carbon::parse($subscription->ends_at)->format('Y-m-d');
                $current_period_start = Carbon::parse($subscription->created_at)->format('Y-m-d');
                $totalAmount = $subscription->stripe_price;
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
        if($totalSeconds < 60){
            return "{$totalSeconds}s";
        }
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
    public static function getWeeklyProductivityReport($teamUserIds)
    {
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

        // Calculate totals for the week to determine UI action
        $totalProductiveSeconds = $summaries->sum('productive_seconds');
        $totalNonproductiveSeconds = $summaries->sum('nonproductive_seconds');
        $totalNeutralSeconds = $summaries->sum('neutral_seconds');
        $totalSeconds = $summaries->sum('total_seconds');

        // Determine UI action based on work type
        $uiAction = "NO_BUTTON";
        
        if ($totalSeconds == 0) {
            // No work at all - show download button
            $uiAction = 'SHOW_DOWNLOAD_BUTTON';
        } elseif ($totalProductiveSeconds == 0 && $totalNonproductiveSeconds == 0 && $totalNeutralSeconds > 0) {
            // Only neutral work - show productivity ratings button
            $uiAction = 'SHOW_PRODUCTIVITY_RATINGS_BUTTON';
        }

        return [
            'days' => $completeReport,
            'ui_action' => $uiAction
        ];
    }
    public static function getTodaysAttendanceCount($teamUserIds)
    {
        $today = Carbon::today()->format('Y-m-d');
        $attendanceCount = UserProductivitySummary::where('date', $today)
            ->whereIn('user_id', $teamUserIds)
            ->count();
            return $attendanceCount;
    }
    public static function hasUsedBacklsh($teamUserIds)
    {
        $usedCount = UserProductivitySummary::whereIn('user_id', $teamUserIds)
            ->where('total_seconds', '>', 0)
            ->count();
            return $usedCount > 0;
    }
    // Add these methods to your existing Helper.php class (app/Helpers/Helper.php)

/**
 * Get projects list for specific user
 * 
 * @param int $userId User ID
 * @param string $startDate Start date
 * @param string $endDate End date
 * @return array Projects assigned to user with details
 */
public static function getProjectsForUser($userId, $startDate, $endDate)
{
    $projects = DB::table('projects')
        ->join('project_members', 'projects.id', '=', 'project_members.project_id')
        ->where('project_members.user_id', $userId)
        ->select(
            'projects.id',
            'projects.name as project_name',
            'projects.status',
            'projects.start_date',
            'projects.end_date'
        )
        ->get();

    $projectsList = [];

    foreach ($projects as $project) {
        $timeSpentByUser = self::calculateProjectTimeByUser($project->id, $userId, $startDate, $endDate);
        $totalTimeTracked = self::calculateTotalProjectTime($project->id, $startDate, $endDate);
        $timeProgressPercentage = $totalTimeTracked > 0 ? (($timeSpentByUser/$totalTimeTracked) * 100) : 0;

        $tasks = self::calculateProjectProgress($project->id);

        $projectsList[] = [
            'project_id' => $project->id,
            'project_name' => $project->project_name,
            'status' => $project->status,
            'time_spent_by_user' => self::convertSecondsInReadableFormat($timeSpentByUser),
            'time_spent_by_user_seconds' => $timeSpentByUser,
            'total_time_tracked' => self::convertSecondsInReadableFormat($totalTimeTracked),
            'total_time_tracked_seconds' => $totalTimeTracked,
            'task_done' => $tasks['completedTasks'],
            'task_assigned' => $tasks['totalTasksAssigned'],
            'time_progress_percentage' => round($timeProgressPercentage,1),
            'start_date' => $project->start_date ? Carbon::parse($project->start_date)->format('d-m-Y') : null,
            'end_date' => $project->end_date ? Carbon::parse($project->end_date)->format('d-m-Y') : null,
        ];
    }

    return $projectsList;
}

/**
 * Get active projects list for entire team
 * 
 * @param array $teamUserIds Array of team user IDs
 * @param string $startDate Start date
 * @param string $endDate End date
 * @return array Active projects with team statistics
 */
public static function getActiveProjectsForTeam($teamUserIds)
{
    // Get all active projects that have team members
    $projects = DB::table('projects')
        ->join('project_members', 'projects.id', '=', 'project_members.project_id')
        ->whereIn('project_members.user_id', $teamUserIds)
        ->where('projects.status', 'ACTIVE')
        ->select(
            'projects.id',
            'projects.name as project_name',
            'projects.status',
            'projects.start_date',
            'projects.end_date'
        )
        ->distinct()
        ->get();

    // Calculate total time tracked by entire team (not just on projects)
    $totalTeamTimeTracked = DB::table('user_activities')
        ->whereIn('user_id', $teamUserIds)
        ->sum(DB::raw('TIMESTAMPDIFF(SECOND, start_datetime, end_datetime)')) ?? 0;

    $projectsList = [];

    foreach ($projects as $project) {
        // Time spent by entire team on this project
        $timeSpentByTeam = self::calculateTotalProjectTime($project->id);
        
        // Calculate percentage of total team time spent on this project
        $percentageOfTotalTime = $totalTeamTimeTracked > 0 
            ? round(($timeSpentByTeam / $totalTeamTimeTracked) * 100, 2) 
            : 0;
        
        // Calculate progress percentage
        $progressPercentage = self::calculateProjectProgress($project->id);

        $projectsList[] = [
            'project_id' => $project->id,
            'project_name' => $project->project_name,
            'status' => $project->status,
            'time_spent_by_team' => self::convertSecondsInReadableFormat($timeSpentByTeam),
            'time_spent_by_team_seconds' => $timeSpentByTeam,
            'percentage_of_total_time' => $percentageOfTotalTime,
            'progress_percentage' => round($progressPercentage, 2),
            'start_date' => $project->start_date ? Carbon::parse($project->start_date)->format('d-m-Y') : null,
            'end_date' => $project->end_date ? Carbon::parse($project->end_date)->format('d-m-Y') : null,
        ];
    }

    // Sort by time spent descending
    usort($projectsList, function($a, $b) {
        return $b['time_spent_by_team_seconds'] - $a['time_spent_by_team_seconds'];
    });

    return $projectsList;
}

    /**
     * Calculate time spent by specific user on a project
     * 
     * @param int $projectId Project ID
     * @param int $userId User ID
     * @param string $startDate Start date
     * @param string $endDate End date
     * @return int Total seconds
     */
    public static function calculateProjectTimeByUser($projectId, $userId, $startDate, $endDate)
    {
        $totalSeconds = DB::table('user_activities')
            ->where('user_id', $userId)
            ->where('project_id', $projectId)
            ->whereBetween('start_datetime', [$startDate, $endDate])
            ->whereRaw("DATE(start_datetime) = DATE(end_datetime)")
            ->sum(DB::raw('TIMESTAMPDIFF(SECOND, start_datetime, end_datetime)'));

        return $totalSeconds ?? 0;
    }

    /**
     * Calculate total time tracked on a project by all users
     * 
     * @param int $projectId Project ID
     * @param string $startDate Start date
     * @param string $endDate End date
     * @return int Total seconds
     */
    public static function calculateTotalProjectTime($projectId)
    {
        $totalSeconds = DB::table('user_activities')
            ->where('project_id', $projectId)
            ->sum(DB::raw('TIMESTAMPDIFF(SECOND, start_datetime, end_datetime)'));

        return $totalSeconds ?? 0;
    }

    /**
     * Calculate project progress based on completed tasks
     * 
     * @param int $projectId Project ID
     * @return float Progress percentage
     */
    public static function calculateProjectProgress($projectId)
    {
        $totalTasks = DB::table('tasks')
            ->where('project_id', $projectId)
            ->count();

        if ($totalTasks === 0) {
            return [
                'totalTasksAssigned' => 0,
                'completedTasks' => 0
            ];
        }

        $completedTasks = DB::table('tasks')
            ->where('project_id', $projectId)
            ->where('status', 'DONE')
            ->count();

        return [
            'totalTasksAssigned' => $totalTasks,
            'completedTasks' => $completedTasks
        ];
    }

    /**
     * Get total projects assigned count
     * 
     * @param int $userId User ID
     * @return int Count
     */
    public static function getTotalProjectsAssigned($userId)
    {
        return DB::table('project_members')
            ->where('user_id', $userId)
            ->count();
    }

    /**
     * Get total tasks assigned count
     * 
     * @param int $userId User ID
     * @return int Count
     */
    public static function getTotalTasksAssigned($userId)
    {
        return DB::table('tasks')
            ->where('assignee_id', $userId)
            ->count();
    }

    /**
     * Get count of overdue tasks for user
     * 
     * @param int $userId User ID
     * @return int Count
     */
    public static function getTasksOverdue($userId, $team = true)
    {
        return DB::table('tasks')
            ->when($team, function ($query) use ($userId) {
                // If $team is true, use whereIn
                $query->whereIn('assignee_id', $userId);
            }, function ($query) use ($userId) {
                // If $team is false, use where
                $query->where('assignee_id', $userId);
            })
            ->where('status', '!=', 'DONE')
            ->where('due_date', '<', Carbon::now())
            ->count();
    }
  public static function getProjectCountByStatus($userId,$status="OVERDUE",$teamUserIds=[])
  {
        $status = strtoupper((string) $status);
        $today = Carbon::now()->toDateString();
        
        $query = DB::table('projects')
            ->where(function ($query) use ($userId,$teamUserIds) {
                // Projects created by the user
                $query->where('created_by',$userId )
                      // OR Projects where the user is a member (using a correlated subquery on the project_members table)
                      ->orWhereExists(function ($subquery) use ($userId,$teamUserIds) {
                          $subquery->select(DB::raw(1))
                                   ->from('project_members')
                                   // FIX: Use whereColumn for correlated query to ensure proper binding
                                   ->whereColumn('project_members.project_id', 'projects.id')
                                   ->whereIn('project_members.user_id', $teamUserIds);
                      });
            });

        if ($status === 'OVERDUE') {
            // An overdue project is an ACTIVE project that is past its end date.
            $query->where('status', 'ACTIVE')
                  ->whereNotNull('end_date')
                  ->where('end_date', '<', $today);
        } else {
            // Filter by one of the standard ENUM statuses (ACTIVE, ON_HOLD, COMPLETED, CANCELLED)
            $validStatuses = ['ACTIVE', 'ON_HOLD', 'COMPLETED', 'CANCELLED'];
            
            if (in_array($status, $validStatuses)) {
                $query->where('status', $status);
            } else {
                // If an invalid status is passed, return 0
                return 0;
            }
        }
        return $query->count();
    }


}
