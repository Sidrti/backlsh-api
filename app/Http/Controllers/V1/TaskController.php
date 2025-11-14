<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTaskRequest;
use App\Http\Requests\UpdateTaskRequest;
use App\Http\Resources\TaskResource;
use App\Models\Project;
use App\Models\Task;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TaskController extends Controller
{
    /**
     * Display a listing of tasks for a specific project.
     */
    public function indexByProject(Request $request, Project $project): JsonResponse
    {
        // Check if user has access to this project
        $user = auth()->user();
        if ($project->created_by !== $user->id && !$project->members->contains($user->id)) {
            return response()->json([
                'status_code' => 2,
                'message' => 'You do not have access to this project',
            ], 403);
        }

        $query = $project->tasks()->with(['assignee', 'project']);

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

        // Filter by priority
        if ($request->has('priority') && $request->priority) {
            $query->where('priority', $request->priority);
        }

        // Filter by member (assignee)
        if ($request->has('member_id') && $request->member_id) {
            $query->where('assignee_id', $request->member_id);
        }

        // Filter by deadline
        if ($request->has('deadline') && $request->deadline) {
            switch ($request->deadline) {
                case 'OVERDUE':
                    $query->where('status', '!=', 'DONE')
                          ->whereNotNull('due_date')
                          ->whereDate('due_date', '<', now());
                    break;
                case 'APPROACHING':
                    $query->where('status', '!=', 'DONE')
                          ->whereNotNull('due_date')
                          ->whereDate('due_date', '>=', now())
                          ->whereDate('due_date', '<=', now()->addDays(3));
                    break;
                case 'ON_TRACK':
                    $query->where('status', '!=', 'DONE')
                          ->whereNotNull('due_date')
                          ->whereDate('due_date', '>', now()->addDays(3));
                    break;
                case 'NO_DEADLINE':
                    $query->whereNull('due_date');
                    break;
            }
        }

        // Sort by
        $sortBy = $request->input('sort_by', 'newest_first');
        switch ($sortBy) {
            case 'oldest_first':
                $query->orderBy('created_at', 'asc');
                break;
            case 'due_date_asc':
                $query->orderBy('due_date', 'asc');
                break;
            case 'due_date_desc':
                $query->orderBy('due_date', 'desc');
                break;
            case 'priority':
                // HIGH > MEDIUM > LOW
                $query->orderByRaw("FIELD(priority, 'HIGH', 'MEDIUM', 'LOW')");
                break;
            case 'newest_first':
            default:
                $query->orderBy('created_at', 'desc');
                break;
        }

        // Pagination
        $perPage = $request->input('per_page', 10);
        $tasks = $query->paginate($perPage);

        return response()->json([
            'status_code' => 1,
            'data' => TaskResource::collection($tasks),
            'pagination' => [
                'current_page' => $tasks->currentPage(),
                'last_page' => $tasks->lastPage(),
                'per_page' => $tasks->perPage(),
                'total' => $tasks->total(),
                'from' => $tasks->firstItem(),
                'to' => $tasks->lastItem(),
            ],
            'message' => 'Tasks fetched successfully',
        ]);
    }

    /**
     * Store a newly created task.
     */
    public function store(StoreTaskRequest $request): JsonResponse
    {
        $project = Project::findOrFail($request->project_id);

        // Check if user has access to this project
        $user = auth()->user();
        if ($project->created_by !== $user->id && !$project->members->contains($user->id)) {
            return response()->json([
                'status_code' => 2,
                'message' => 'You do not have access to this project',
            ], 403);
        }

        $task = Task::create([
            'project_id' => $request->project_id,
            'name' => $request->name,
            'description' => $request->description,
            'status' => $request->status ?? 'TODO',
            'priority' => $request->priority ?? 'MEDIUM',
            'due_date' => $request->due_date,
            'assignee_id' => $request->assignee_id,
        ]);

        $task->load(['project', 'assignee']);

        return response()->json([
            'status_code' => 1,
            'data' => new TaskResource($task),
            'message' => 'Task created successfully',
        ], 201);
    }

    /**
     * Display the specified task.
     */
    public function show(Task $task): JsonResponse
    {
        // Check if user has access to this task's project
        $user = auth()->user();
        $project = $task->project;
        
        if ($project->created_by !== $user->id && !$project->members->contains($user->id)) {
            return response()->json([
                'status_code' => 2,
                'message' => 'You do not have access to this task',
            ], 403);
        }

        $task->load(['project', 'assignee']);

        return response()->json([
            'status_code' => 1,
            'data' => new TaskResource($task),
            'message' => 'Task fetched successfully',
        ]);
    }

    /**
     * Update the specified task.
     */
    public function update(UpdateTaskRequest $request, Task $task): JsonResponse
    {
        // Check if user has access to this task's project
        $user = auth()->user();
        $project = $task->project;
        
        if ($project->created_by !== $user->id && !$project->members->contains($user->id)) {
            return response()->json([
                'status_code' => 2,
                'message' => 'You do not have access to this task',
            ], 403);
        }

        // Update only the fields that are present in the request
        $updateData = [];
        
        if ($request->has('project_id')) {
            $updateData['project_id'] = $request->project_id;
        }
        if ($request->has('name')) {
            $updateData['name'] = $request->name;
        }
        if ($request->has('description')) {
            $updateData['description'] = $request->description;
        }
        if ($request->has('status')) {
            $updateData['status'] = $request->status;
        }
        if ($request->has('priority')) {
            $updateData['priority'] = $request->priority;
        }
        if ($request->has('due_date')) {
            $updateData['due_date'] = $request->due_date;
        }
        if ($request->has('assignee_id')) {
            $updateData['assignee_id'] = $request->assignee_id;
        }

        $task->update($updateData);

        $task->load(['project', 'assignee']);

        return response()->json([
            'status_code' => 1,
            'data' => new TaskResource($task),
            'message' => 'Task updated successfully',
        ]);
    }

    /**
     * Remove the specified task.
     */
    public function destroy(Task $task): JsonResponse
    {
        // Check if user is the project creator
        $user = auth()->user();
        $project = $task->project;
        
        if ($project->created_by !== $user->id) {
            return response()->json([
                'status_code' => 2,
                'message' => 'Only the project creator can delete tasks',
            ], 403);
        }

        $task->delete();

        return response()->json([
            'status_code' => 1,
            'message' => 'Task deleted successfully',
        ]);
    }
}
