<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProjectRequest;
use App\Http\Requests\UpdateProjectRequest;
use App\Http\Resources\ProjectResource;
use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProjectController extends Controller
{
    /**
     * Display a listing of projects.
     */
    public function index(Request $request)
    {
        $user = auth()->user();
        
        // Base query for user's projects
        $baseQuery = Project::query()
            ->where(function ($q) use ($user) {
                $q->where('created_by', $user->id)
                  ->orWhereHas('members', function ($memberQuery) use ($user) {
                      $memberQuery->where('users.id', $user->id);
                  });
            });

        // Calculate stats
        $stats = [
            'total_projects' => (clone $baseQuery)->count(),
            'active' => (clone $baseQuery)->where('status', 'ACTIVE')->count(),
            'completed' => (clone $baseQuery)->where('status', 'COMPLETED')->count(),
            'overdue' => (clone $baseQuery)
                ->where('status', '!=', 'COMPLETED')
                ->whereNotNull('end_date')
                ->whereDate('end_date', '<', now())
                ->count(),
        ];

        // Main query with relationships
        $query = Project::with(['creator', 'members'])
            ->withCount([
                'tasks',
                'members',
                'tasks as tasks_completed_count' => function ($query) {
                    $query->where('status', 'DONE');
                }
            ])
            ->where(function ($q) use ($user) {
                $q->where('created_by', $user->id)
                  ->orWhereHas('members', function ($memberQuery) use ($user) {
                      $memberQuery->where('users.id', $user->id);
                  });
            });

        // Search by name or description
        if ($request->has('search') && $request->search) {
            $searchTerm = $request->search;
            $query->where(function ($q) use ($searchTerm) {
                $q->where('name', 'like', '%' . $searchTerm . '%')
                  ->orWhere('description', 'like', '%' . $searchTerm . '%');
            });
        }

        // Filter by status
        if ($request->has('status') && $request->status) {
            $query->where('status', $request->status);
        }

        // Filter by deadline
        if ($request->has('deadline') && $request->deadline) {
            switch ($request->deadline) {
                case 'OVERDUE':
                    $query->where('status', '!=', 'COMPLETED')
                          ->whereNotNull('end_date')
                          ->whereDate('end_date', '<', now());
                    break;
                case 'APPROACHING':
                    $query->where('status', '!=', 'COMPLETED')
                          ->whereNotNull('end_date')
                          ->whereDate('end_date', '>=', now())
                          ->whereDate('end_date', '<=', now()->addDays(7));
                    break;
                case 'ON_TRACK':
                    $query->where('status', '!=', 'COMPLETED')
                          ->whereNotNull('end_date')
                          ->whereDate('end_date', '>', now()->addDays(7));
                    break;
                case 'NO_DEADLINE':
                    $query->whereNull('end_date');
                    break;
            }
        }

        // Sort by
        $sortBy = $request->input('sort_by', 'newest_first');
        switch ($sortBy) {
            case 'oldest_first':
                $query->orderBy('created_at', 'asc');
                break;
            case 'a_z':
                $query->orderBy('name', 'asc');
                break;
            case 'z_a':
                $query->orderBy('name', 'desc');
                break;
            case 'most_tasks':
                $query->orderBy('tasks_count', 'desc');
                break;
            case 'newest_first':
            default:
                $query->orderBy('created_at', 'desc');
                break;
        }

        // Pagination
        $perPage = $request->input('per_page', 10);
        $projects = $query->paginate($perPage);

        return response()->json([
            'status_code' => 1,
            'data' => ProjectResource::collection($projects),
            'stats' => $stats,
            'pagination' => [
                'current_page' => $projects->currentPage(),
                'last_page' => $projects->lastPage(),
                'per_page' => $projects->perPage(),
                'total' => $projects->total(),
                'from' => $projects->firstItem(),
                'to' => $projects->lastItem(),
            ],
            'message' => 'Projects fetched successfully',
        ]);
    }

    /**
     * Store a newly created project.
     */
    public function store(StoreProjectRequest $request): JsonResponse
    {
        $project = Project::create([
            'name' => $request->name,
            'description' => $request->description,
            'status' => $request->status ?? 'ACTIVE',
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
            'created_by' => auth()->id(),
        ]);

        // Add project members (creator + additional members)
        $memberIds = $request->input('member_ids', []);
        // Always include the creator
        $memberIds[] = auth()->id();
        // Remove duplicates
        $memberIds = array_values(array_unique($memberIds));
        // Attach all members
        $project->members()->attach($memberIds);

        $project->load(['creator', 'members', 'tasks']);

        return response()->json([
            'status_code' => 1,
            'data' => new ProjectResource($project),
            'message' => 'Project created successfully',
        ], 200);
    }

    /**
     * Display the specified project.
     */
    public function show(Project $project): JsonResponse
    {
        // Check if user has access to this project
        $user = auth()->user();
        if ($project->created_by !== $user->id && !$project->members->contains($user->id)) {
            return response()->json([
                'status_code' => 2,
                'message' => 'You do not have access to this project',
            ], 403);
        }

        $project->load(['creator', 'members', 'tasks.assignee']);
        $project->loadCount([
            'tasks',
            'members',
            'tasks as todo_tasks_count' => function ($query) {
                $query->where('status', 'TODO');
            },
            'tasks as in_progress_tasks_count' => function ($query) {
                $query->where('status', 'IN_PROGRESS');
            },
            'tasks as done_tasks_count' => function ($query) {
                $query->where('status', 'DONE');
            },
            'tasks as high_priority_tasks_count' => function ($query) {
                $query->where('priority', 'HIGH');
            },
            'tasks as medium_priority_tasks_count' => function ($query) {
                $query->where('priority', 'MEDIUM');
            },
            'tasks as low_priority_tasks_count' => function ($query) {
                $query->where('priority', 'LOW');
            }
        ]);

        return response()->json([
            'status_code' => 1,
            'data' => new ProjectResource($project),
            'message' => 'Project fetched successfully',
        ]);
    }

    /**
     * Update the specified project.
     */
    public function update(UpdateProjectRequest $request, Project $project): JsonResponse
    {
        // Check if user is the creator
        if ($project->created_by !== auth()->id()) {
            return response()->json([
                'status_code' => 2,
                'message' => 'Only the project creator can update this project',
            ], 403);
        }

        // Update only the fields that are present in the request
        $updateData = [];
        
        if ($request->has('name')) {
            $updateData['name'] = $request->name;
        }
        if ($request->has('description')) {
            $updateData['description'] = $request->description;
        }
        if ($request->has('status')) {
            $updateData['status'] = $request->status;
        }
        if ($request->has('start_date')) {
            $updateData['start_date'] = $request->start_date;
        }
        if ($request->has('end_date')) {
            $updateData['end_date'] = $request->end_date;
        }

        $project->update($updateData);

        // // Sync project members if provided; always include creator
        // if ($request->has('member_ids')) {
        //     $memberIds = $request->input('member_ids', []);
        //     $memberIds[] = $project->created_by;
        //     $memberIds = array_values(array_unique($memberIds));
        //     $project->members()->sync($memberIds);
        // }

        $project->load(['creator', 'members', 'tasks']);

        return response()->json([
            'status_code' => 1,
            'data' => new ProjectResource($project),
            'message' => 'Project updated successfully',
        ]);
    }

    /**
     * Remove the specified project.
     */
    public function destroy(Project $project): JsonResponse
    {
        // Check if user is the creator
        if ($project->created_by !== auth()->id()) {
            return response()->json([
                'status_code' => 2,
                'message' => 'Only the project creator can delete this project',
            ], 403);
        }

        $project->delete();

        return response()->json([
            'status_code' => 1,
            'message' => 'Project deleted successfully',
        ]);
    }
}
