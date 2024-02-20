<?php

namespace App\Http\Controllers\V1\Website;

use App\Http\Controllers\Controller;
use App\Models\Process;
use App\Models\UserProcessRating;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class UserProcessRatingController extends Controller
{
    public function createProcessRating(Request $request)
    {
        $request->validate([
            'process_id' => 'required|exists:processes,id',
            'rating' => 'required|in:PRODUCTIVE,NONPRODUCTIVE,NEUTRAL',
        ]);

        $userId = auth()->user()->id;
        // Check if a rating for the user and process already exists
        $existingRating = UserProcessRating::where('user_id', $userId)
            ->where('process_id', $request->input('process_id'))
            ->first();

        if ($existingRating) {
            // If a rating exists, update the rating
            $existingRating->rating = $request->input('rating');
            $existingRating->save();

            return response()->json([
                'status_code' => 1,
                'message' => 'User process rating updated successfully.',
                'data' => $existingRating,
            ]);
        } else {
            // If no rating exists, create a new rating entry
            $userProcessRating = new UserProcessRating();
            $userProcessRating->user_id = $userId;
            $userProcessRating->process_id = $request->input('process_id');
            $userProcessRating->rating = $request->input('rating');
            $userProcessRating->save();

            return response()->json([
                'status_code' => 1,
                'data' => $userProcessRating,
                'message' => 'User process rating added successfully.',
            ]);
        }
    }
    public function fetchProcessRating(Request $request)
    {
        $processRatings = Process::leftJoin('user_process_ratings','user_process_ratings.process_id','processes.id')
        ->leftJoin('user_activities', 'processes.id', '=', 'user_activities.process_id')
        ->select('processes.id','processes.process_name','processes.type','user_process_ratings.rating',  DB::raw('SUM(TIMESTAMPDIFF(SECOND, user_activities.start_datetime, user_activities.end_datetime)/3600) AS total_time'))
        ->distinct('processes.process_name')
        ->groupBy('processes.id', 'processes.process_name', 'processes.type', 'user_process_ratings.rating')
        ->orderByDesc('total_time')
        ->get();

        return response()->json([
            'status_code' => 1,
            'data' => $processRatings,
        ]);
    }
}