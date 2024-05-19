<?php

namespace App\Http\Controllers\V1\Website;

use App\Helpers\Helper;
use App\Http\Controllers\Controller;
use App\Models\Process;
use App\Models\UserActivity;
use App\Models\UserScreenshot;
use App\Models\UserSubActivity;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    public function fetchUserReportsByDate(Request $request) 
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
        ]);

        $startDate = Carbon::parse($request->input('start_date', Carbon::now()->subDays(7)));
        $endDate = Carbon::parse($request->input('end_date', Carbon::now()))->endOfDay();
        
       
        $userId = $request->input('user_id');
       
        $process = $this->getProcessDataWithScreenshots($userId,$startDate,$endDate);
        $totalProductiveHours = Helper::calculateTotalHoursByUserId($userId,$startDate,$endDate,'PRODUCTIVE');
        $userWorkingTrend = $this->getProductiveNonProductiveTimeByEachDay($userId,$startDate,$endDate,false);
        $userAttendance = Helper::getUserAttendance($userId,$startDate,$endDate);

        $data =  [
            'process' => $process,
            'total_productive_hours' => $totalProductiveHours,
            'user_attendance' => $userAttendance,
            'working_trend' => $userWorkingTrend
        ];

        return response()->json(['status_code' => 1, 'data' => $data]);
    }
    public function fetchUserSubActivities(Request $request) 
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'process_id' =>'required|exists:processes,id',
        ]);

        $userId = $request->input('user_id');
        $processId = $request->input('process_id');
        $startDate = Carbon::parse($request->input('start_date', Carbon::now()->subDays(7)));
        $endDate = Carbon::parse($request->input('end_date', Carbon::now()));

        $userSubActivitiesByProcessId = $this->getSubActivityDataWithScreenshots($userId,$processId,$startDate,$endDate);

        $data =  [
            'user_sub_activities' => $userSubActivitiesByProcessId,
        ];

        return response()->json(['status_code' => 1, 'data' => $data]);
    }
    private function getProcessDataWithScreenshots($userId, $startDate, $endDate)
    {
        $processData = UserActivity::join('processes', 'user_activities.process_id', '=', 'processes.id')
        ->select(
            'user_activities.process_id',
            'processes.process_name',
            'user_activities.productivity_status',
            'processes.type',
            DB::raw('round(SUM(TIMESTAMPDIFF(SECOND, user_activities.start_datetime, user_activities.end_datetime))/3600,1) AS total_time'),
        )
        ->where('user_activities.user_id', $userId)
        ->where('processes.process_name','!=','-1')
        ->where('processes.process_name','!=','LockApp')
        ->where('processes.process_name','!=','Idle')
        ->whereBetween('user_activities.start_datetime', [$startDate, $endDate])
        ->whereBetween('user_activities.end_datetime', [$startDate, $endDate])
        ->groupBy('user_activities.process_id', 'processes.process_name','user_activities.productivity_status','processes.type')
        ->orderByDesc('total_time')
        ->get();

        foreach($processData as $item) {
            $processId = $item->process_id;
            $screenshots = UserScreenshot::whereBetween('created_at', [$startDate, $endDate])
            ->where('process_id',$processId)
            ->where('user_id',$userId)
            ->get();

            $item->screenshots = $screenshots;
        }

        return $processData;
    }

    private function getSubActivityDataWithScreenshots($userId,$processId,$startDate, $endDate)
    {
        $processData = UserSubActivity::join('user_activities', 'user_activities.id', '=', 'user_sub_activities.user_activity_id')
        ->leftJoin('processes','processes.id','user_sub_activities.process_id')
        ->select(
            'user_activities.process_id',
            'user_sub_activities.productivity_status',
            'user_sub_activities.website_url',
            'user_sub_activities.start_datetime',
            'user_sub_activities.end_datetime',
            'processes.process_name',
            'processes.type',
            DB::raw('round(SUM(TIMESTAMPDIFF(SECOND, user_sub_activities.start_datetime, user_sub_activities.end_datetime))/3600,1) AS total_time'),
        )
        ->where('user_activities.user_id', $userId)
        ->where('user_activities.process_id', $processId)
        ->where('user_sub_activities.website_url','!=','')
        ->where('user_sub_activities.website_url','!=','-1')
        ->where('processes.process_name','!=','-1')
        ->orderBy('total_time','desc')
        ->whereBetween('user_sub_activities.start_datetime', [$startDate, $endDate])
        ->groupBy('user_activities.process_id', 'user_sub_activities.website_url','user_sub_activities.productivity_status','user_sub_activities.end_datetime','user_sub_activities.start_datetime','processes.process_name','processes.type')
        ->paginate(30);

        foreach($processData as $item) {
            $processId = $item->process_id;
            $screenshots = UserScreenshot::whereBetween('created_at', [$startDate, $endDate])
            ->where('process_id',$processId)
            ->where('user_id',$userId)
            // ->where('website_url',$item->website_url)
            ->get();

            $item->screenshots = $screenshots;
        }

        return $processData;
    }

    private function getProductiveNonProductiveTimeByEachDay($userId, $startDate, $endDate, $teamRecords = true)
    {
        $userActivities = UserActivity::join('users', 'users.id', '=', 'user_activities.user_id')
        ->join('processes','processes.id','user_activities.process_id')
        ->where(function ($query) use ($userId,$teamRecords) {
            if ($teamRecords) {
                $query->where('users.parent_user_id', $userId)
                      ->orWhere('users.id', $userId);
            } else {
                $query->where('users.id', $userId);
            }
        })
        ->where('processes.process_name','!=','-1')
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
                case 'NONPRODUCTIVE':
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
    
}
