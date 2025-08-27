<?php

namespace App\Http\Controllers\V1\Website;

use App\Helpers\Helper;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserActivity;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RealtimeController extends Controller
{
    public function fetchRealtimeUpdates(Request $request)
    {
        $userId = auth()->user()->id;
        $todayOnlineMemberCount = Helper::getMembersOnlineCount($userId);
        if ($request->has('filter')) {
            if ($request->filter == 'PRODUCTIVE_FROM_30_MIN') {
                $realtimeUpdates = $this->getOnlineMembersUsageProductiveFilter($userId, 0.5, 'PRODUCTIVE');
            } else {
                $realtimeUpdates = $this->getOnlineMembersUsageProductiveFilter($userId, 0.5, 'UNPRODUCTIVE');
            }
        } else {
            $tenMinutesAgo = Carbon::now()->subMinutes(10);
            $realtimeUpdates = $this->getOnlineMembersUsageProductiveFilter($userId);
        }

        $nonProductiveUserCount = $this->countNonProductiveUsers($userId);
        $todayTime = Carbon::today();
        $membersPresentToday = Helper::getTodaysAttendanceCount(
            User::where(function ($query) use ($userId) {
                $query->where('parent_user_id', $userId)
                    ->orWhere('id', $userId);
            })->pluck('id')->toArray()
        );

        $data =  [
            'today_online_member_count' => $todayOnlineMemberCount,
            'realtime_update' => $realtimeUpdates,
            'non_productive_users_count' => $nonProductiveUserCount,
            'member_present_today' => $membersPresentToday
        ];

        return response()->json(['status_code' => 1, 'data' => $data]);
    }
    private function getOnlineMembersUsage($userId, $time)
    {
        $users = User::with(['userActivities' => function ($query) {
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
                'name' => $user->name,
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
   private function getOnlineMembersUsageProductiveFilter($userId, $threshold = 0.5, $status = null)
{
    $thirtyMinutesAgo = now()->subMinutes(30);
    $tenMinutesAgo = now()->subMinutes(10);

    $query = UserActivity::select([
            'user_activities.user_id',
            'users.name',
            'processes.process_name',
            'processes.id as process_id',
            'processes.icon as icon_url',
            'processes.type as process_type',
            'user_activities.productivity_status',
            'user_activities.created_at',
            'users.profile_picture',
            DB::raw("CASE 
                WHEN user_activities.created_at >= '{$tenMinutesAgo}' THEN 'ONLINE' 
                ELSE 'OFFLINE' 
            END as online_status")
        ])
        ->leftJoin('users', 'users.id', '=', 'user_activities.user_id')
        ->leftJoin('processes', 'processes.id', '=', 'user_activities.process_id')
        ->where(function ($query) use ($userId) {
            $query->where('users.parent_user_id', $userId)
                  ->orWhere('users.id', $userId);
        })
        ->where('user_activities.start_datetime', '>=', Carbon::today())
        ->whereIn('user_activities.id', function ($subquery) use ($thirtyMinutesAgo) {
            $subquery->select(DB::raw('MAX(id)'))
                     ->from('user_activities')
                     ->where('start_datetime', '>=', Carbon::today())
                     ->groupBy('user_id');
        })
        ->orderByRaw("CASE WHEN user_activities.created_at >= '{$tenMinutesAgo}' THEN 0 ELSE 1 END")
        ->orderBy('user_activities.created_at', 'desc');

    $latestActivities = $query->get();

    // Calculate productivity ratios and apply filters
    $result = $latestActivities->map(function ($activity) use ($userId, $thirtyMinutesAgo, $threshold, $status) {
        $activity->icon_url = asset('storage/' . ($process->icon_url ??config(('app.process_default_image'))));
        $activity->profile_picture = (new User([
        'profile_picture' => $activity->profile_picture
    ]))->profile_picture;
        // Get productivity stats for this user
        $productivityStats = UserActivity::where('user_id', $activity->user_id)
            ->where('start_datetime', '>=', $thirtyMinutesAgo)
            ->selectRaw('
                COUNT(*) as total_count,
                SUM(CASE WHEN productivity_status = "PRODUCTIVE" THEN 1 ELSE 0 END) as productive_count
            ')
            ->first();

        $productiveRatio = $productivityStats->total_count > 0 
            ? ($productivityStats->productive_count / $productivityStats->total_count) 
            : 0;

        // Apply productivity filter
        if ($status === 'PRODUCTIVE' && $productiveRatio < $threshold) {
            return null;
        }
        if ($status === 'UNPRODUCTIVE' && $productiveRatio >= $threshold) {
            return null;
        }

        return [
            'user_id' => $activity->user_id,
            'name' => $activity->name,
            'process_name' => $activity->process_name,
            'productivity_status' => $activity->productivity_status,
            'profile_picture' => $activity->profile_picture,
            'process_id' => $activity->process_id,
            'process_type' => $activity->process_type,
            'last_working_datetime' => $activity->created_at,
            'status' => $activity->online_status,
            'productive_ratio' => round($productiveRatio, 2),
            'icon_url' => $activity->icon_url
        ];
    })->filter()->values();

    return $result->all();
}

    private function countNonProductiveUsers($userId, $threshold = 0.5)
    {
        return count($this->getOnlineMembersUsageProductiveFilter($userId, $threshold, 'UNPRODUCTIVE'));
    }
}
