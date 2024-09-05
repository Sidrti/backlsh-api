<?php

namespace App\Http\Controllers\V1\Website;

use App\Models\AttendanceSchedule;
use App\Helpers\Helper;
use App\Http\Controllers\Controller;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;

class AttendanceController extends Controller
{
    public function createAttendanceSchedule(Request $request)
    {
        $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date', 
            'start_time' => 'required',
            'end_time' => 'required',
            'user_ids' => 'required|array',
            'user_ids.*' => 'exists:users,id',
            'min_hours' => 'required|numeric' 
        ]);

        $schedule = new AttendanceSchedule();
        $schedule->start_date = $request->start_date; 
        $schedule->end_date = $request->end_date;
        $schedule->start_time = $request->start_time;
        $schedule->end_time = $request->end_time;
        $schedule->min_hours = $request->min_hours;
        $schedule->save();

        $schedule->users()->attach($request->user_ids);

        return response()->json([
            'status_code' => 1,
            'message' => 'Attendance schedule created',
        ]);
    }
    public function fetchAttendance(Request $request)
    {
        $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);
    
        $userId = auth()->user()->id;
    
        $teamMembers = User::where(function ($query) use ($userId) {
            $query->where('users.parent_user_id', $userId)
                  ->orWhere('users.id', $userId);
        })->get();
    
        $attendanceRecords = [];
    
        // Iterate over each team member
        foreach ($teamMembers as $user) {
            $score = 0;
            $totalScore = 0;
            $startDate = Carbon::parse($request->input('start_date'));
            $endDate = Carbon::parse($request->input('end_date'));
            $userAttendance = [];
    
            // Iterate over each date in the date range
            for ($date = $startDate; $date->lte($endDate); $date->addDay()) {
                $totalScore++;
                $attendanceStatus = 'ABSENT'; // Default attendance status
    
                // Check if there's an attendance schedule for the current date
                $schedule = AttendanceSchedule::where('start_date', '<=', $date)
                    ->where('end_date', '>=', $date)
                    ->whereHas('users', function ($query) use ($user) {
                        $query->where('user_id', $user->id);
                    })
                    ->first();
    
                $dateEndOfDay = $date->copy()->endOfDay();
                if ($schedule) {
                    $totalHoursWorked = Helper::calculateTotalHoursByUserId($user->id,$date,$dateEndOfDay);
                    // Determine the attendance status based on the minimum hours criteria
                    $attendanceStatus = ($totalHoursWorked >= $schedule->min_hours) ? 'PRESENT' : (($totalHoursWorked > 0) ? 'HALF_DAY' : 'ABSENT');
                    ($totalHoursWorked >= $schedule->min_hours) ? $score++ : (($totalHoursWorked > 0) ? $score = $score + 0.5 : $score);
                    $scheduleExists = true;
                }
                else {
                    $totalHoursWorked = Helper::calculateTotalHoursByUserId($user->id,$date,$dateEndOfDay);
                    $attendanceStatus = ($totalHoursWorked > 1) ? 'PRESENT' : 'ABSENT';
                    ($totalHoursWorked > 1) ? $score++ : $score;
                    $scheduleExists = false;
                }
                
                // Add the attendance record to the user's attendance array
                $userAttendance[] = [
                    'date' => $date->toDateString(),
                    'totalHoursWorked' => $totalHoursWorked,
                    'attendance_status' => $attendanceStatus,
                  
                ];
            }
    
            // Add user's attendance to the main attendanceRecords array
            $attendanceRecords[] = [
                'user_id' => $user->id,
                'user_name' => $user->name,
                'email' => $user->email,
                'schedule_exists' => $scheduleExists,
                'score' => $score,
                'total_score' => $totalScore,
                'attendance' => $userAttendance,
            ];
        }
    
        return response()->json([
            'status_code' => 1,
            'data' => $attendanceRecords,
            'message' => 'Attendance fetched',
        ]);
    }
}