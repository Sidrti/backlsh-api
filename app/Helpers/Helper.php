<?php

namespace App\Helpers;

use App\Models\User;
use App\Models\UserActivity;
use Carbon\Carbon;
use File;

class Helper
{
    public static function computeType($process)
    {
        if($process == 'firefox' || $process == 'chrome')
        {
            return 'BROWSER';
        }
        else
        {
            return 'APPLICATION';
        }
    }
    public static function computeActivityProductivityStatus($process)
    {
        return 'PRODUCTIVE';
    }
    public static function computeSubActivityProductivityStatus($url)
    {
        return 'NON PRODUCTIVE';
    }
    public static function saveImageToServer($file,$dir)
    {
        $path = public_path() . $dir;
        if (!File::exists($path)) {
            File::makeDirectory($path, $mode = 0777, true, true);
        }

        $filename = rand(10000,100000).'_'.time().'_'.$file->getClientOriginalName();
        $file->move($path, $filename);
        $filePath = $dir.$filename;

        return $filePath;
    }
    public static function sendEmail($to,$subject,$body)
    {
        $headers = "MIME-Version: 1.0" . "\r\n";
        $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
       // mail($to, $subject, $body,$headers);
    }
    public static function calculateTotalHoursByUserId($userId,$startDate,$endDate,$status = null)
    {
        // $userActivities = UserActivity::where('user_id', $userId)
        // ->whereRaw("DATE(start_datetime) BETWEEN ? AND ?", [$startDate, $endDate])
        // ->get();
        $userActivities = UserActivity::where('user_id', $userId)
        ->whereBetween('start_datetime', [$startDate, $endDate])
        ->get();

        $filteredActivities = $status ? $userActivities->where('productivity_status', $status) : $userActivities;

        return round($filteredActivities->sum(function ($activity) {
            return Carbon::parse($activity->end_datetime)->diffInSeconds(Carbon::parse($activity->start_datetime)) / 3600;
        }), 2).' Hours';
    }
    public static function calculateTotalHoursByParentId($userId,$startDate,$endDate,$status = null)
    {
        $userActivities = UserActivity::join('users','users.id','=','user_activities.user_id')
        ->where(function ($query) use ($userId) {
            $query->where('users.parent_user_id', $userId)
                  ->orWhere('users.id', $userId);
        })
        ->whereRaw("DATE(user_activities.start_datetime) BETWEEN ? AND ?", [$startDate, $endDate])
        ->get();

        $filteredActivities = $status ? $userActivities->where('productivity_status', $status) : $userActivities;

        return round($filteredActivities->sum(function ($activity) {
            return Carbon::parse($activity->end_datetime)->diffInSeconds(Carbon::parse($activity->start_datetime)) / 3600;
        }), 1).' Hours';
    }
    public static function getUserAttendance($userId,$startDate,$endDate)
    {
        $daysPresent = 0;
        $totalDays = 0;
        // Iterate over each day of the month
        for ($date = $startDate; $date->lte($endDate); $date->addDay()) {
            // Get the user activities for the current day
            $userActivities = UserActivity::where('user_id', $userId)
                ->whereDate('start_datetime', $date->toDateString())
                ->get();
    
            // Calculate the total working hours for the day
            $totalHours = 0;
            foreach ($userActivities as $activity) {
                $hours = Carbon::parse($activity->end_datetime)->diffInSeconds(Carbon::parse($activity->start_datetime)) / 3600;
                $totalHours += $hours;
            }
    
            // If the total working hours for the day exceed 4, increment the days present counter
            if ($totalHours > 4) {
                $daysPresent++;
            }
            $totalDays++;
        }
    
        return ['days_present' => $daysPresent, 'total_days' =>$totalDays];
    }
    public static function getDomainFromUrl($url)
    {
        $parsedUrl = parse_url($url);
        $domain = isset($parsedUrl['host']) ? $parsedUrl['host'] : '';

        return $domain;
    }
    public static function getMembersOnlineCount($userId)
    {
        $thresholdTime = Carbon::now()->subMinutes(10); 
        $today = Carbon::today();
    
        $onlineMembersCount = UserActivity::whereDate('user_activities.start_datetime', $today)
            ->join('users','users.id','user_activities.user_id')
            ->where('start_datetime', '>=', $thresholdTime)
            ->where(function ($query) use ($userId) {
                $query->where('users.parent_user_id', $userId)
                      ->orWhere('users.id', $userId);
            })
            ->distinct('user_activities.user_id')
            ->count();
    
        return $onlineMembersCount;
    }
    public static function createNewUser($name,$email,$password,$loginType,$role,$parentId=0)  //loginType - 1 (Normal),  2 - Google,  3 - linkedin
    {
        $user = User::create([
            'name' => $name,
            'email' => $email,
            'password' => bcrypt($password),
            'login_type' => $loginType,
            'role' => $role,
            'is_verified' => $loginType == 2 || $loginType == 3 ? 1 : 0,
            'parent_user_id' => $parentId
        ]);

        return $user;
    }
}
