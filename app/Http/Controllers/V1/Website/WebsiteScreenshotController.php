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
            // 'start_time' => 'nullable|string|date_format:h:i A',
            // 'end_time' => 'nullable|string|date_format:h:i A|after_or_equal:start_time',
            'timezone_offset_minutes' => 'required|integer'
        ]);

        $timezoneOffset = $request->input('timezone_offset_minutes'); // In minutes
        $startDate = Carbon::parse($request->input('start_date', Carbon::now()->subDays(7)))
            ->startOfDay(); // Adjust to the start of the day after timezone conversion

        // Parse and adjust end date and time
        $endDate = Carbon::parse($request->input('end_date', Carbon::now()))
            ->endOfDay();

        $startTime = $request->has('start_time')
            ? Carbon::createFromFormat('h:i A', $request->input('start_time'))
            ->addMinutes($timezoneOffset) // Adjust for client's timezone
            ->format('H:i:s')
            : Carbon::now()->addMinutes($timezoneOffset)->format('H:i:s'); // Adjust current time to UTC


        // Parse and adjust end time
        $endTime = $request->has('end_time')
            ? Carbon::createFromFormat('h:i A', $request->input('end_time'))
            ->addMinutes($timezoneOffset) // Adjust for client's timezone
            ->format('H:i:s')
            : Carbon::now()->addMinutes($timezoneOffset)->addHour()->format('H:i:s');

        $screenshots = UserScreenshot::where('user_screenshots.user_id', $request->input('user_id'))
            ->select('user_screenshots.*', 'processes.id as process_id', 'processes.process_name', 'processes.type')
            ->join('processes', 'processes.id', 'user_screenshots.process_id')
            ->whereBetween('user_screenshots.created_at', [$startDate, $endDate])
            ->WhereTime('user_screenshots.created_at', '>=', $startTime)
            ->WhereTime('user_screenshots.created_at', '<=', $endTime)
            ->get();


        $data =  [
            'screenshots' => $screenshots
        ];
        return response()->json(['status_code' => 1, 'data' => $data]);
    }
}
