<?php

namespace App\Http\Controllers\V1\Website;

use App\Helpers\Helper;
use App\Http\Controllers\Controller;
use App\Models\UserActivity;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function fetchAdminDashboard(Request $request)
    {
        $startDate = $request->input('start_date', Carbon::now()->subDays(7)->format('Y-m-d'));
        $endDate = $request->input('end_date', Carbon::now()->format('Y-m-d'));
        $userId = auth()->user()->id;

        $totalHoursWorked = Helper::calculateTotalHoursByParentId($userId,$startDate,$endDate);
        $totalProductiveHours = Helper::calculateTotalHoursByParentId($userId,$startDate,$endDate,'PRODUCTIVE');
        $totalNonProductiveHours = Helper::calculateTotalHoursByParentId($userId,$startDate,$endDate,'NON_PRODUCTIVE');
        $totalNeutralHours = Helper::calculateTotalHoursByParentId($userId,$startDate,$endDate,'NEUTRAL');

        $teamWorkingTrend = $this->getProductiveNonProductiveTimeByEachDay($userId,$startDate,$endDate,true);

        $todayOnlineMemberCount = Helper::getMembersOnlineCount($userId);
        $todayTeamAttendance = $this->getTeamAttendanceToday($userId);
        $topMembers = $this->getTopMostProductiveMembers($userId);

        $data =  [
            'total_time_worked' => $totalHoursWorked,
            'total_productive_hours' => $totalProductiveHours,
            'total_non_productive_hours' => $totalNonProductiveHours,
            'total_neutral_hours' => $totalNeutralHours,
            'team_working_trend' => $teamWorkingTrend,
            'today_online_member_count' => $todayOnlineMemberCount,
            'today_team_attendance' => $todayTeamAttendance,
            'top_members' => $topMembers
        ];
        return response()->json(['status_code' => 1, 'data' => $data]);
    }
    public function fetchUserDashboard(Request $request)
    {
        $startDate = $request->input('start_date', Carbon::now()->subDays(7)->format('Y-m-d'));
        $endDate = $request->input('end_date', Carbon::now()->format('Y-m-d'));
        $userId = auth()->user()->id;

        $totalHoursWorked = Helper::calculateTotalHoursByUserId($userId,$startDate,$endDate);
        $totalProductiveHours = Helper::calculateTotalHoursByUserId($userId,$startDate,$endDate,'PRODUCTIVE');
        $totalNonProductiveHours = Helper::calculateTotalHoursByUserId($userId,$startDate,$endDate,'NON_PRODUCTIVE');
        $totalNeutralHours = Helper::calculateTotalHoursByUserId($userId,$startDate,$endDate,'NEUTRAL');

        $userWorkingTrend = $this->getProductiveNonProductiveTimeByEachDay($userId,$startDate,$endDate,false);

        $monthUserAttendance = $this->getUserMonthAttendance($userId);
        $userThisMonthRank = $this->getUserRankOfmonth($userId);
        $userPeekHours = $this->getUserPeekHours($userId);

        $data =  [
            'total_time_worked' => $totalHoursWorked,
            'total_productive_hours' => $totalProductiveHours,
            'total_non_productive_hours' => $totalNonProductiveHours,
            'total_neutral_hours' => $totalNeutralHours,
            'user_working_trend' => $userWorkingTrend,
            'month_user_attendance' => $monthUserAttendance,
            'month_user_rank' => $userThisMonthRank,
            'user_peek_hours' => $userPeekHours
        ];
        return response()->json(['status_code' => 1, 'data' => $data]);
    }
    private function getProductiveNonProductiveTimeByEachDay($userId, $startDate, $endDate, $teamRecords = true)
    {
        $userActivities = UserActivity::join('users', 'users.id', '=', 'user_activities.user_id')
        // ->where('users.parent_user_id', $userId)
        // ->orWhere('users.id',$userId)
        // ->where('user_activities.user_id', $userId)
        ->where(function ($query) use ($userId,$teamRecords) {
            if ($teamRecords) {
                $query->where('users.parent_user_id', $userId)
                      ->orWhere('users.id', $userId);
            } else {
                $query->where('users.id', $userId);
            }
        })
        ->whereRaw("DATE(user_activities.start_datetime) BETWEEN ? AND ?", [$startDate, $endDate])
        ->get();

        $result = [];
        $nonProductiveHours = 0;
        $neutralHours = 0; 
        $productiveHours = 0;
        foreach ($userActivities as $activity) {
            $date = Carbon::parse($activity->start_datetime)->format('d-m-Y');
            $hours = Carbon::parse($activity->end_datetime)->diffInSeconds(Carbon::parse($activity->start_datetime)) / 3600;
          
            switch ($activity->productivity_status) {
                case 'PRODUCTIVE':
                    $productiveHours = $hours;
                    break;
                case 'NON_PRODUCTIVE':
                    $nonProductiveHours = $hours;
                    break;
                case 'NEUTRAL':
                    $neutralHours = $hours;
                    break;
                default:
                    break;
            }

            if (!isset($result[$date])) {
                $result[$date] = [
                    'date' => $date,
                    'productive' => $productiveHours,
                    'nonproductive' => $nonProductiveHours,
                    'neutral' => $neutralHours,
                ];
            } else {
                $result[$date]['productive'] += $productiveHours;
                $result[$date]['nonproductive'] += $nonProductiveHours;
                $result[$date]['neutral'] += $neutralHours;
            }
            $nonProductiveHours = 0;
            $neutralHours = 0; 
            $productiveHours = 0;
            $hours = 0;
        }

        foreach ($result as &$day) {
            $day['productive'] = round($day['productive'], 1);
            $day['nonproductive'] = round($day['nonproductive'], 1);
            $day['neutral'] = round($day['neutral'], 1);
        }

        return array_values($result);
    }

    private function getUserMonthAttendance($userId)
    {
        $firstDayOfMonth = Carbon::now()->startOfMonth();
        $lastDayOfMonth = Carbon::today();

        $userAttendance = Helper::getUserAttendance($userId,$firstDayOfMonth,$lastDayOfMonth);
        return $userAttendance;
    }
    private function getTeamAttendanceToday($userId)
    {
        $today = Carbon::today();

        $onlineMembersCount = UserActivity::whereDate('user_activities.start_datetime', $today)
            ->join('users','users.id','user_activities.user_id')
            ->where(function ($query) use ($userId) {
                $query->where('users.parent_user_id', $userId)
                      ->orWhere('users.id', $userId);
            })
           // ->orWhere('users.id',$userId)
            ->distinct('user_activities.user_id')
            ->count();
    
        return $onlineMembersCount;
    }
    private function getTopMostProductiveMembers($userId)
    {
        $today = Carbon::today();
        //$today = '2024-02-05';
        $topMembers = UserActivity::select('user_id', 'users.name','users.email', DB::raw('SUM(CASE WHEN productivity_status = "productive" THEN 1 ELSE 0 END) as productive_count'))
            ->join('users','users.id','user_activities.user_id')
            ->whereDate('start_datetime', $today)
            ->where(function ($query) use ($userId) {
                $query->where('users.parent_user_id', $userId)
                    ->orWhere('users.id', $userId);
            })
            ->groupBy('user_id', 'users.name', 'users.email')
            ->orderByDesc('productive_count')
            ->limit(5)
            ->get();

        return $topMembers;
    }
    private function getUserRankOfmonth($userId)
    {
        $firstDayOfMonth = Carbon::now()->startOfMonth();
        $today = Carbon::today();

        $topMembers = UserActivity::select('user_id', DB::raw('SUM(CASE WHEN productivity_status = "productive" THEN 1 ELSE 0 END) as productive_count'))
            ->join('users','users.id','user_activities.user_id')
            ->where(function ($query) use ($userId) {
                $query->where('users.parent_user_id', $userId)
                    ->orWhere('users.id', $userId);
            })
            ->whereRaw("DATE(user_activities.start_datetime) BETWEEN ? AND ?", [$firstDayOfMonth, $today])
            ->groupBy('user_id')
            ->orderByDesc('productive_count')
            ->get();

            $index = 1;
            foreach ($topMembers as $member) {
                if ($member->user_id === $userId) {
                    return $index;
                }
                $index++;
            }
            return null;
    }
    private function getUserPeekHours($userId)
    {
        $startDate = Carbon::now()->subDays(10)->startOfDay();
        $endDate = Carbon::now()->endOfDay();
    
        $userActivities = UserActivity::where('user_id', $userId)
            ->whereBetween('start_datetime', [$startDate, $endDate])
            ->where('productivity_status', 'productive')
            ->get();
    
        // Initialize an array to store productivity counts for each hour
        $hourlyProductivity = array_fill(0, 24, 0);
    
        // Iterate through the user activities and update the hourly productivity counts
        foreach ($userActivities as $activity) {
            $startHour = Carbon::parse($activity->start_datetime)->hour;
            $endHour = Carbon::parse($activity->end_datetime)->hour;
    
            // Increment productivity counts for each hour within the activity duration
            for ($hour = $startHour; $hour <= $endHour; $hour++) {
                $hourlyProductivity[$hour]++;
            }
        }
    
        // Find the peak hours with highest productivity counts
        $maxProductivity = max($hourlyProductivity);
        $peakHours = [];
        for ($hour = 0; $hour < 24; $hour++) {
            if ($hourlyProductivity[$hour] === $maxProductivity) {
                // Format hours with AM/PM
                $startHourFormatted = $hour >= 12 ? ($hour === 12 ? '12 PM' : ($hour - 12) . ' PM') : ($hour === 0 ? '12 AM' : $hour . ' AM');
                $endHourFormatted = (($hour + 1) % 24) >= 12 ? ((($hour + 1) % 24) === 12 ? '12 PM' : ((($hour + 1) % 24) - 12) . ' PM') : ((($hour + 1) % 24) === 0 ? '12 AM' : (($hour + 1) % 24) . ' AM');
    
                // Construct the formatted peak hour string
                $peakHours[] = "$startHourFormatted - $endHourFormatted";
            }
        }
    
        return $peakHours;
    }    

}
