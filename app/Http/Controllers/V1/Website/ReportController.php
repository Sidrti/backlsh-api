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
        $endDate = Carbon::parse($request->input('end_date', Carbon::now()));
        
        $userId = $request->input('user_id');

        $process = $this->getProcessDataWithScreenshots($userId,$startDate,$endDate);
        $totalProductiveHours = Helper::calculateTotalHoursByUserId($userId,$startDate,$endDate,'PRODUCTIVE');
        $userAttendance = Helper::getUserAttendance($userId,$startDate,$endDate);

        $data =  [
            'process' => $process,
            'total_productive_hours' => $totalProductiveHours,
            'user_attendance' => $userAttendance
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
            DB::raw('round(SUM(TIMESTAMPDIFF(SECOND, user_activities.start_datetime, user_activities.end_datetime))/3600,1) AS total_time'),
        )
        ->where('user_activities.user_id', $userId)
        ->whereBetween('user_activities.start_datetime', [$startDate, $endDate])
        ->groupBy('user_activities.process_id', 'processes.process_name','user_activities.productivity_status')
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
        ->select(
            'user_activities.process_id',
            'user_sub_activities.productivity_status',
            'user_sub_activities.website_url',
            'user_sub_activities.start_datetime',
            'user_sub_activities.end_datetime',
            DB::raw('round(SUM(TIMESTAMPDIFF(SECOND, user_sub_activities.start_datetime, user_sub_activities.end_datetime))/3600,1) AS total_time'),
        )
        ->where('user_activities.user_id', $userId)
        ->where('user_activities.process_id', $processId)
        ->whereBetween('user_sub_activities.start_datetime', [$startDate, $endDate])
        ->groupBy('user_activities.process_id', 'user_sub_activities.website_url','user_sub_activities.productivity_status','user_sub_activities.end_datetime','user_sub_activities.start_datetime')
        ->get();

        foreach($processData as $item) {
            $processId = $item->process_id;
            $screenshots = UserScreenshot::whereBetween('created_at', [$startDate, $endDate])
            ->where('process_id',$processId)
            ->where('user_id',$userId)
            ->where('website_url',$item->website_url)
            ->get();

            $item->screenshots = $screenshots;
        }

        return $processData;
    }
    
}
