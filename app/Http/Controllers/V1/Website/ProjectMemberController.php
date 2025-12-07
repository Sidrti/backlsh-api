<?php

namespace App\Http\Controllers\V1\Website;

use App\Http\Controllers\Controller;
use App\Http\Requests\AddProjectMemberRequest;
use App\Http\Resources\UserResource;
use App\Models\Project;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class ProjectMemberController extends Controller
{
    /**
     * Display a listing of project members.
     */
    public function index(Project $project): JsonResponse
    {
        // Check if user has access to this project
        $user = auth()->user();
        if ($project->created_by !== $user->id && !$project->members->contains($user->id)) {
            return response()->json([
                'status_code' => 2,
                'message' => 'You do not have access to this project',
            ], 403);
        }

        $members = $project->members;

        return response()->json([
            'status_code' => 1,
            'data' => UserResource::collection($members),
            'message' => 'Project members fetched successfully',
        ]);
    }

    /**
     * Add a member to the project.
     */
    public function store(AddProjectMemberRequest $request, Project $project): JsonResponse
    {
        // Check if user is the project creator
        if ($project->created_by !== auth()->id()) {
            return response()->json([
                'status_code' => 2,
                'message' => 'Only the project creator can add members',
            ], 403);
        }

        $userId = $request->user_id;

        // Check if user is already a member
        if ($project->members->contains($userId)) {
            return response()->json([
                'status_code' => 2,
                'message' => 'User is already a member of this project',
            ], 400);
        }

        $project->members()->attach($userId);

        $user = User::find($userId);

        return response()->json([
            'status_code' => 1,
            'data' => new UserResource($user),
            'message' => 'Member added successfully',
        ], 200);
    }

    /**
     * Remove a member from the project.
     */
    public function destroy(Project $project, User $user): JsonResponse
    {
        // Check if user is the project creator
        if ($project->created_by !== auth()->id()) {
            return response()->json([
                'status_code' => 2,
                'message' => 'Only the project creator can remove members',
            ], 403);
        }

        // Prevent removing the creator
        if ($project->created_by === $user->id) {
            return response()->json([
                'status_code' => 2,
                'message' => 'Cannot remove the project creator from the project',
            ], 400);
        }

        // Check if user is a member
        if (!$project->members->contains($user->id)) {
            return response()->json([
                'status_code' => 2,
                'message' => 'User is not a member of this project',
            ], 400);
        }

        $project->members()->detach($user->id);

        return response()->json([
            'status_code' => 1,
            'message' => 'Member removed successfully',
        ]);
    }
}
