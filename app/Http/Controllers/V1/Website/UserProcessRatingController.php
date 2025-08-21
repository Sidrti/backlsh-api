<?php

namespace App\Http\Controllers\V1\Website;

use App\Http\Controllers\Controller;
use App\Models\Process;
use App\Models\User;
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
        $searchQuery = $request->get('search');
        $currentUser = auth()->user();
        $teamUserIds = User::where('id', $currentUser->id)
            ->orWhere('parent_user_id', $currentUser->id)
            ->when($currentUser->parent_user_id, function ($query) use ($currentUser) {
                return $query->orWhere('parent_user_id', $currentUser->parent_user_id);
            })
            ->pluck('id');

        $query = Process::join('user_activities', function ($join) use ($teamUserIds) {
            $join->on('processes.id', '=', 'user_activities.process_id')
                ->whereIn('user_activities.user_id', $teamUserIds);
        })
            ->leftJoin('user_process_ratings', function ($join) use ($currentUser) {
                $join->on('user_process_ratings.process_id', '=', 'processes.id')
                    ->where('user_process_ratings.user_id', '=', $currentUser->id);
            })
            ->select(
                'processes.id',
                'processes.process_name',
                'processes.type',
                 DB::raw("CONCAT('" . asset('storage') . "/', COALESCE(processes.icon, '" . config('app.process_default_image') . "')) AS icon_url"),
                DB::raw('COALESCE(user_process_ratings.rating, "NEUTRAL") AS rating'),
                DB::raw('SUM(TIMESTAMPDIFF(SECOND, user_activities.start_datetime, user_activities.end_datetime) / 3600) AS total_time')
            )
            ->distinct('processes.process_name')
            ->groupBy('processes.id', 'processes.process_name','processes.icon', 'processes.type', 'user_process_ratings.rating')
            ->where('processes.process_name', '!=', '-1')
             ->where('processes.process_name', '!=', 'idle');

            if ($searchQuery) {
                $query->where(function ($q) use ($searchQuery) {
                    $q->where('processes.process_name', 'like', '%' . $searchQuery . '%')
                    ->orWhere('processes.type', 'like', '%' . $searchQuery . '%');
                });
            }

            $query->paginate(30);
            $processRatings = $query->orderByDesc('total_time')->paginate(30);


        return response()->json([
            'status_code' => 1,
            'data' => $processRatings,
        ]);
    }
}
