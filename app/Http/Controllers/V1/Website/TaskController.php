<?php

namespace App\Http\Controllers\V1\Website;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTaskRequest;
use App\Http\Requests\UpdateTaskRequest;
use App\Http\Resources\TaskResource;
use App\Models\Project;
use App\Models\Task;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Models\User;

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

        $query = $project->tasks()->with(['assignee', 'project', 'userActivities']);

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

        try {
            $task = $this->createOrUpdateTask($project, $request->validated());
            $task->load(['project', 'assignee']);

            return response()->json([
                'status_code' => 1,
                'data' => new TaskResource($task),
                'message' => 'Task created successfully',
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'status_code' => 2,
                'message' => $e->getMessage(),
            ], 422);
        }
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

        $project = $request->has('project_id') ? Project::findOrFail($request->project_id) : $task->project;

        try {
            $task = $this->createOrUpdateTask($project, $request->validated(), $task);
            $task->load(['project', 'assignee']);

            return response()->json([
                'status_code' => 1,
                'data' => new TaskResource($task),
                'message' => 'Task updated successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status_code' => 2,
                'message' => $e->getMessage(),
            ], 422);
        }
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

    /**
     * Import tasks from a CSV file.
     */
    public function import(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'file' => 'required|file|mimes:csv,txt',
            'project_id' => 'required|exists:projects,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status_code' => 2,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $project = Project::findOrFail($request->project_id);
        $user = auth()->user();

        if ($project->created_by !== $user->id && !$project->members->contains($user->id)) {
            return response()->json([
                'status_code' => 2,
                'message' => 'You do not have access to this project',
            ], 403);
        }

        $path = $request->file('file')->getRealPath();
        $file = fopen($path, 'r');

        if ($file === false) {
            return response()->json(['status_code' => 2, 'message' => 'Failed to open the uploaded file.'], 500);
        }

        $header = fgetcsv($file);
        if ($header === false) {
            fclose($file);
            return response()->json(['status_code' => 2, 'message' => 'CSV file is empty or invalid.'], 422);
        }

        $tasks_created_count = 0;
        $errors = [];
        $row_number = 1;

        while (($row = fgetcsv($file)) !== false) {
            $row_number++;
            $data = array_combine($header, $row);

            try {
                $rowValidator = Validator::make($data, [
                    'name' => 'required|string|max:255',
                    'description' => 'nullable|string',
                    'due_date' => 'nullable|date',
                    'assignee_email' => 'nullable|email|exists:users,email',
                ]);

                if ($rowValidator->fails()) {
                    throw new \Exception($rowValidator->errors()->first());
                }

                $assignee_id = null;
                if (!empty($data['assignee_email'])) {
                    $assignee = User::where('email', $data['assignee_email'])->first();
                    $assignee_id = $assignee->id;
                }

                $valid_statuses = ['TODO', 'IN_PROGRESS', 'DONE'];
                $valid_priorities = ['LOW', 'MEDIUM', 'HIGH'];

                $taskData = [
                    'name' => $data['name'],
                    'description' => $data['description'] ?? null,
                    'status' => isset($data['status']) && in_array($data['status'], $valid_statuses) ? $data['status'] : 'TODO',
                    'priority' => isset($data['priority']) && in_array($data['priority'], $valid_priorities) ? $data['priority'] : 'MEDIUM',
                    'due_date' => !empty($data['due_date']) ? date('Y-m-d H:i:s', strtotime($data['due_date'])) : null,
                    'assignee_id' => $assignee_id,
                    'update_or_create' => true,
                ];

                $this->createOrUpdateTask($project, $taskData, null, true);

                $tasks_created_count++;
            } catch (\Exception $e) {
                $errors[] = [
                    'row' => $row_number,
                    'error' => $e->getMessage(),
                ];
            }
        }

        fclose($file);

        $response = [
            'status_code' => 1,
            'message' => 'Import process completed.',
            'summary' => [
                'tasks_created' => $tasks_created_count,
                'tasks_failed' => count($errors),
            ],
            'errors' => $errors,
        ];

        return response()->json($response);
    }

    /**
     * Create or update a task with validation.
     *
     * @param Project $project
     * @param array $data
     * @param Task|null $task
     * @return Task
     * @throws \Exception
     */
    protected function createOrUpdateTask(Project $project, array $data, ?Task $task = null, bool $importCall = false): Task
    {
        if (!empty($data['assignee_id'])) {
            $assigneeId = $data['assignee_id'];

            $isMember = $project->members()->where('user_id', $assigneeId)->exists();

            if (!$isMember && $project->created_by != $assigneeId) {
                if (!$importCall) {
                     throw new \Exception('Assigned user is not a member of this project. Add this member to the project first.');
                }
                else {
                    $data['assignee_id'] = null;
                }
            }
        }

        $taskData = [
            'project_id' => $project->id,
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'status' => $data['status'] ?? 'TODO',
            'priority' => $data['priority'] ?? 'MEDIUM',
            'due_date' => $data['due_date'] ?? null,
            'assignee_id' => $data['assignee_id'] ?? null,
        ];

        if ($task) {
            $task->update($taskData);
            return $task;
        }

        if (isset($data['update_or_create']) && $data['update_or_create']) {
            return Task::updateOrCreate(
                ['project_id' => $project->id, 'name' => $data['name']],
                $taskData
            );
        }

        return Task::create($taskData);
    }
}
