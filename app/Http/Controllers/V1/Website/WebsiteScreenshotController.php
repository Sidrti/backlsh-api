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

class WebsiteScreenshotController extends Controller
{
    public function fetchScreenshotsByUser(Request $request)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'start_time' => 'nullable|string|date_format:h:i A',
            'end_time' => 'nullable|string|date_format:h:i A|after_or_equal:start_time',
        ]);
        $startDate = Carbon::parse($request->input('start_date', Carbon::now()->subDays(7)));
        $endDate = Carbon::parse($request->input('end_date', Carbon::now()));
        $startTime = $request->has('start_time') ? Carbon::createFromFormat('h:i A', $request->input('start_time'))->format('H:i:s') : Carbon::now()->format('H:00:00');
        $endTime = $request->has('end_time') ? Carbon::createFromFormat('h:i A', $request->input('end_time'))->format('H:i:s') : Carbon::now()->addHour()->format('H:00:00');

        $screenshots = UserScreenshot::where('user_screenshots.user_id', $request->input('user_id'))
        ->join('processes','processes.id','user_screenshots.process_id')
        ->whereBetween('user_screenshots.created_at', [$startDate->startOfDay()->format('Y-m-d H:i:s'), $endDate->endOfDay()->format('Y-m-d H:i:s')])
        ->whereTime('user_screenshots.created_at', '>=', $startTime)
        ->whereTime('user_screenshots.created_at', '<=', $endTime)
        ->get();

        $data =  [
            'screenshots' => $screenshots
        ];
        return response()->json(['status_code' => 1, 'data' => $data]);
    }
}
