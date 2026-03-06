<?php

namespace App\Http\Controllers\V1\Website;

use App\Http\Controllers\Controller;
use App\Models\Issue;
use App\Models\Project;
use App\Models\Task;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class IssuesController extends Controller
{
    /**
     * Display a listing of issues for a specific project.
     */
    public function indexByProject(Request $request, Project $project): JsonResponse
    {
        $user = auth()->user();
        if ($project->created_by !== $user->id && !$project->members->contains($user->id)) {
            return response()->json([
                'status_code' => 2,
                'message' => 'You do not have access to this project',
            ], 403);
        }

        $query = $project->issues()->with(['reporter', 'assignee', 'task']);

        if ($request->has('status') && $request->status) {
            $query->where('status', $request->status);
        }

        if ($request->has('severity') && $request->severity) {
            $query->where('severity', $request->severity);
        }

        if ($request->has('type') && $request->type) {
            $query->where('type', $request->type);
        }

        if ($request->has('assigned_to') && $request->assigned_to) {
            $query->where('assigned_to', $request->assigned_to);
        }

        if ($request->has('search') && $request->search) {
            $searchTerm = $request->search;
            $query->where(function ($q) use ($searchTerm) {
                $q->where('title', 'like', '%' . $searchTerm . '%')
                  ->orWhere('description', 'like', '%' . $searchTerm . '%');
            });
        }

        $perPage = $request->input('per_page', 10);
        $issues = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return response()->json([
            'status_code' => 1,
            'data' => $issues->items(),
            'pagination' => [
                'current_page' => $issues->currentPage(),
                'last_page' => $issues->lastPage(),
                'per_page' => $issues->perPage(),
                'total' => $issues->total(),
            ],
            'message' => 'Issues fetched successfully',
        ]);
    }

    /**
     * Store a newly created issue.
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'project_id' => 'required|exists:projects,id',
            'task_id' => 'nullable|exists:tasks,id',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'severity' => 'nullable|string|in:LOW,MEDIUM,HIGH',
            'type' => 'nullable|string|in:BUG,IMPROVEMENT,REQUIREMENT',
            'status' => 'nullable|string|in:TODO,IN_PROGRESS,DONE',
            'assigned_to' => 'nullable|exists:users,id',
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

        if ($request->has('task_id') && $request->task_id) {
            $task = Task::findOrFail($request->task_id);
            if ($task->project_id !== $project->id) {
                return response()->json([
                    'status_code' => 2,
                    'message' => 'Task does not belong to this project',
                ], 422);
            }
        }

        try {
            $issue = Issue::create([
                'project_id' => $request->project_id,
                'task_id' => $request->task_id,
                'reported_by' => $user->id,
                'assigned_to' => $request->assigned_to,
                'title' => $request->title,
                'description' => $request->description,
                'severity' => $request->severity ?? 'MEDIUM',
                'type' => $request->type ?? 'BUG',
                'status' => $request->status ?? 'TODO',
            ]);

            $issue->load(['reporter', 'assignee', 'task', 'project']);

            return response()->json([
                'status_code' => 1,
                'data' => $issue,
                'message' => 'Issue created successfully',
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'status_code' => 2,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Display the specified issue.
     */
    public function show(Issue $issue): JsonResponse
    {
        $user = auth()->user();
        $project = $issue->project;

        if ($project->created_by !== $user->id && !$project->members->contains($user->id)) {
            return response()->json([
                'status_code' => 2,
                'message' => 'You do not have access to this issue',
            ], 403);
        }

        $issue->load(['reporter', 'assignee', 'task', 'project']);

        return response()->json([
            'status_code' => 1,
            'data' => $issue,
            'message' => 'Issue fetched successfully',
        ]);
    }

    /**
     * Update the specified issue.
     */
    public function update(Request $request, Issue $issue): JsonResponse
    {
        $user = auth()->user();
        $project = $issue->project;

        if ($project->created_by !== $user->id && !$project->members->contains($user->id)) {
            return response()->json([
                'status_code' => 2,
                'message' => 'You do not have access to this issue',
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'title' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'severity' => 'nullable|string|in:LOW,MEDIUM,HIGH',
            'type' => 'nullable|string|in:BUG,IMPROVEMENT,REQUIREMENT',
            'status' => 'nullable|string|in:TODO,IN_PROGRESS,DONE',
            'assigned_to' => 'nullable|exists:users,id',
            'task_id' => 'nullable|exists:tasks,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status_code' => 2,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $data = [];

            if ($request->has('title')) {
                $data['title'] = $request->title;
            }
            if ($request->has('description')) {
                $data['description'] = $request->description;
            }
            if ($request->has('severity')) {
                $data['severity'] = $request->severity;
            }
            if ($request->has('type')) {
                $data['type'] = $request->type;
            }
            if ($request->has('status')) {
                $data['status'] = $request->status;
                if ($request->status === 'DONE' && $issue->status !== 'DONE') {
                    $data['resolved_at'] = now();
                } elseif ($request->status !== 'DONE' && $issue->status === 'DONE') {
                    $data['resolved_at'] = null;
                    $data['reopen_count'] = $issue->reopen_count + 1;
                }
            }
            if ($request->has('assigned_to')) {
                $data['assigned_to'] = $request->assigned_to;
            }
            if ($request->has('task_id')) {
                if ($request->task_id) {
                    $task = Task::findOrFail($request->task_id);
                    if ($task->project_id !== $project->id) {
                        return response()->json([
                            'status_code' => 2,
                            'message' => 'Task does not belong to this project',
                        ], 422);
                    }
                }
                $data['task_id'] = $request->task_id;
            }

            $issue->update($data);
            $issue->load(['reporter', 'assignee', 'task', 'project']);

            return response()->json([
                'status_code' => 1,
                'data' => $issue,
                'message' => 'Issue updated successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status_code' => 2,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Remove the specified issue.
     */
    public function destroy(Issue $issue): JsonResponse
    {
        $user = auth()->user();
        $project = $issue->project;

        if ($project->created_by !== $user->id) {
            return response()->json([
                'status_code' => 2,
                'message' => 'Only the project creator can delete issues',
            ], 403);
        }

        $issue->delete();

        return response()->json([
            'status_code' => 1,
            'message' => 'Issue deleted successfully',
        ]);
    }

    /**
     * Bulk update issues (status or assignee).
     */
    public function bulkUpdate(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'issue_ids' => 'required|array',
            'issue_ids.*' => 'exists:issues,id',
            'assigned_to' => 'nullable|exists:users,id',
            'status' => 'nullable|string|in:TODO,IN_PROGRESS,DONE',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status_code' => 2,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $issueIds = $request->input('issue_ids');
        $assignedTo = $request->input('assigned_to');
        $status = $request->input('status');

        if (is_null($assignedTo) && is_null($status)) {
            return response()->json([
                'status_code' => 2,
                'message' => 'Either assigned_to or status must be provided.',
            ], 422);
        }

        $issues = Issue::whereIn('id', $issueIds)->get();
        $user = auth()->user();
        $project = null;

        foreach ($issues as $issue) {
            if (is_null($project)) {
                $project = $issue->project;
            } elseif ($project->id !== $issue->project_id) {
                return response()->json([
                    'status_code' => 2,
                    'message' => 'All issues must belong to the same project.',
                ], 422);
            }
        }

        if ($project->created_by !== $user->id && !$project->members->contains($user->id)) {
            return response()->json([
                'status_code' => 2,
                'message' => 'You do not have access to this project',
            ], 403);
        }

        $updateData = [];
        if (!is_null($assignedTo)) {
            $updateData['assigned_to'] = $assignedTo;
        }
        if (!is_null($status)) {
            $updateData['status'] = $status;
            if ($status === 'DONE') {
                $updateData['resolved_at'] = now();
            } else {
                $updateData['resolved_at'] = null;
            }
        }

        Issue::whereIn('id', $issueIds)->update($updateData);

        return response()->json([
            'status_code' => 1,
            'message' => 'Issues updated successfully.',
        ]);
    }
}
