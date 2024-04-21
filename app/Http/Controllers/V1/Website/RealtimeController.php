<?php

namespace App\Http\Controllers\V1\Website;

use App\Helpers\Helper;
use App\Http\Controllers\Controller;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RealtimeController extends Controller
{
    public function fetchRealtimeUpdates(Request $request)
    {
        $userId = auth()->user()->id;
        $todayOnlineMemberCount = Helper::getMembersOnlineCount($userId);
        if($request->has('filter')) {
            if($request->filter == 'PRODUCTIVE_FROM_30_MIN') {
                $realtimeUpdates = $this->getOnlineMembersUsageProductiveFilter($userId,0.5,'PRODUCTIVE');
            }
            else {
                $realtimeUpdates = $this->getOnlineMembersUsageProductiveFilter($userId,0.5,'UNPRODUCTIVE');
            } 
        }
        else {
            $tenMinutesAgo = Carbon::now()->subMinutes(10);
            $realtimeUpdates = $this->getOnlineMembersUsage($userId,$tenMinutesAgo);
        }
       
        $nonProductiveUserCount = $this->countNonProductiveUsers($userId);
        $todayTime = Carbon::today();
        $membersPresentToday = count($this->getOnlineMembersUsage($userId,$todayTime));

        $data =  [
            'today_online_member_count' => $todayOnlineMemberCount,
            'realtime_update' => $realtimeUpdates,
            'non_productive_users_count' => $nonProductiveUserCount,
            'member_present_today' => $membersPresentToday
        ];

        return response()->json(['status_code' => 1, 'data' => $data]);
    }
    private function getOnlineMembersUsage($userId,$time)
    {
        // $usersProcess = DB::table('user_activities as ua')
        //     ->select('u.id as user_id', 'u.name', 'p.process_name', 'ua.productivity_status')
        //     ->join('users as u', 'u.id', '=', 'ua.user_id')
        //     ->join('processes as p', 'p.id', '=', 'ua.process_id')
        //     ->join(DB::raw('(SELECT user_id, MAX(start_datetime) AS latest_datetime
        //                     FROM user_activities
        //                     GROUP BY user_id) AS latest_activities'), function($join) {
        //                         $join->on('ua.user_id', '=', 'latest_activities.user_id')
        //                             ->on('ua.start_datetime', '=', 'latest_activities.latest_datetime');
        //                     })
        //     ->where(function ($query) use ($userId) {
        //         $query->where('u.parent_user_id', $userId)
        //             ->orWhere('u.id', $userId);
        //     })
        //     ->where('ua.end_datetime', '>=', $time) // Filter for last activity within 10 minutes
        //     ->groupBy('u.id', 'u.name', 'p.process_name', 'ua.productivity_status')
        //     ->get();
        $users = User::with(['userActivities' => function ($query) {
            // $query->latest()->take(1);
            $query->latest()->take(1)->with('process');
        }])
        ->where(function ($query) use ($userId) {
            $query->where('parent_user_id', $userId)
                ->orWhere('id', $userId);
        })
        ->get();
        
        $now = Carbon::now();
        
        $users->transform(function ($user) use ($now) {
            $lastActivity = $user->userActivities->first();
        
            if ($lastActivity && $lastActivity->created_at->diffInMinutes($now) < 10) {
                $user->status = 'ONLINE';
            } else {
                $user->status = 'OFFLINE';
            }
            $data = [
                'user_id' => $user->id,
                'name' =>$user->name,
                'profile_picture' => $user->profile_picture
            ];
        
            if ($lastActivity) {
                $data['process_id'] = $lastActivity->process_id;
                $data['process_name'] = $lastActivity->process->process_name;
                $data['process_type'] = $lastActivity->process->type;
                $data['productivity_status'] = $lastActivity->productivity_status;
                $data['last_working_datetime'] = $lastActivity->created_at;
                $data['status'] = $user->status;
            } else {
                $data['process_id'] = null;
                $data['process_name'] = null;
                $data['process_type'] = null;
                $data['productivity_status'] = null;
                $data['last_working_datetime'] = null;
                $data['status'] = $user->status;
            }
        
            return $data;
        });
        return $users;
    }
    private function getOnlineMembersUsageProductiveFilter($userId,$threshold=0.5,$status)
    {
        // Calculate the timestamp 30 minutes ago
        $thirtyMinutesAgo = Carbon::now()->subMinutes(30);

        // Query to fetch users and their productivity status within the last 30 minutes
        $usersData = DB::table('user_activities as ua')
            ->select('u.id as user_id', 'u.name', 'p.process_name', 'ua.productivity_status','usa.title','usa.website_url')
            ->join('users as u', 'u.id', '=', 'ua.user_id')
            ->join('processes as p', 'p.id', '=', 'ua.process_id')
            ->leftJoin('user_sub_activities as usa', 'usa.user_activity_id', '=', 'ua.id')
            ->where(function ($query) use ($userId) {
                $query->where('u.parent_user_id', $userId)
                    ->orWhere('u.id', $userId);
            })
            ->where('ua.start_datetime', '>=', $thirtyMinutesAgo) // Filter for activity within the last 30 minutes
            ->get();

        // Calculate productivity ratio for each user
        $userRatios = [];
        foreach ($usersData as $userData) {
            $user_id = $userData->user_id;
            if (!isset($userRatios[$user_id])) {
                $userRatios[$user_id] = [
                    'productive_count' => 0,
                    'total_count' => 0,
                    'name' => $userData->name,
                    'user_id' => $userData->user_id,
                    'process_name' => $userData->process_name,
                    'title' => $userData->title,
                    'website_url' => $userData->website_url,
                    'productivity_status' => $userData->productivity_status
                ];
            }
            if ($userData->productivity_status == 'PRODUCTIVE') {
                $userRatios[$user_id]['productive_count']++;
            }
            $userRatios[$user_id]['total_count']++;
        }

        // Filter users with productivity ratio >= 70%
        $mostlyProductiveUsers = [];
        foreach ($userRatios as $user_id => $ratio) {
            $productiveRatio = $ratio['productive_count'] / $ratio['total_count'];
            if ($productiveRatio >= $threshold && $status == 'PRODUCTIVE') {
                $mostlyProductiveUsers[] = [
                    'user_id' => $ratio['user_id'],
                    'name' =>$ratio['name'],
                    'process_name' =>$ratio['process_name'],
                    'productivity_status' => $ratio['productivity_status'],
                    'title' => $ratio['title'],
                    'website_url' => $ratio['website_url'],
                    'productive_ratio' => $productiveRatio,
                    
                ];
            }
            else if ($productiveRatio < $threshold && $status == 'UNPRODUCTIVE') {
                $mostlyProductiveUsers[] = [
                    'user_id' => $ratio['user_id'],
                    'name' =>$ratio['name'],
                    'process_name' =>$ratio['process_name'],
                    'productivity_status' => $ratio['productivity_status'],
                    'title' => $ratio['title'],
                    'website_url' => $ratio['website_url'],
                    'productive_ratio' => $productiveRatio,
                    
                ];
            }
        }

        return $mostlyProductiveUsers;
    }
    private function getNonProductiveUsersData($userId, $threshold = 0.5)
    {
        // Calculate the timestamp 30 minutes ago
        $thirtyMinutesAgo = Carbon::now()->subMinutes(30);
    
        // Query to fetch users and their productivity status within the last 30 minutes
        $usersData = DB::table('user_activities as ua')
            ->select('u.id as user_id', 'u.name', 'p.process_name', 'ua.productivity_status', 'usa.title', 'usa.website_url')
            ->join('users as u', 'u.id', '=', 'ua.user_id')
            ->join('processes as p', 'p.id', '=', 'ua.process_id')
            ->leftJoin('user_sub_activities as usa', 'usa.user_activity_id', '=', 'ua.id')
            ->where(function ($query) use ($userId) {
                $query->where('u.parent_user_id', $userId)
                    ->orWhere('u.id', $userId);
            })
            ->where('ua.start_datetime', '>=', $thirtyMinutesAgo) // Filter for activity within the last 30 minutes
            ->get();
    
        // Calculate productivity ratio for each user
        $userRatios = [];
        foreach ($usersData as $userData) {
            $user_id = $userData->user_id;
            if (!isset($userRatios[$user_id])) {
                $userRatios[$user_id] = [
                    'productive_count' => 0,
                    'total_count' => 0,
                    'name' => $userData->name,
                    'user_id' => $userData->user_id,
                    'process_name' => $userData->process_name,
                    'title' => $userData->title,
                    'website_url' => $userData->website_url,
                    'productivity_status' => $userData->productivity_status
                ];
            }
            if ($userData->productivity_status == 'NON_PRODUCTIVE') {
                $userRatios[$user_id]['productive_count']++;
            }
            $userRatios[$user_id]['total_count']++;
        }
    
        // Filter users with non-productivity ratio >= threshold
        $mostlyNonProductiveUsers = [];
        foreach ($userRatios as $user_id => $ratio) {
            $nonProductiveRatio = $ratio['productive_count'] / $ratio['total_count'];
            if ($nonProductiveRatio >= $threshold) {
                $mostlyNonProductiveUsers[] = [
                    'user_id' => $ratio['user_id'],
                    'name' => $ratio['name'],
                    'process_name' => $ratio['process_name'],
                    'productivity_status' => $ratio['productivity_status'],
                    'title' => $ratio['title'],
                    'website_url' => $ratio['website_url'],
                    'productive_ratio' => $nonProductiveRatio,
                ];
            }
        }
    
        return $mostlyNonProductiveUsers;
    }
    
    private function countNonProductiveUsers($userId, $threshold = 0.5)
    {
        return count($this->getNonProductiveUsersData($userId, $threshold));
    }
    

}
