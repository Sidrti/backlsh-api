<?php

namespace App\Http\Controllers\V1\Website;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProjectProcessRuleResource;
use App\Models\Project;
use App\Models\ProjectProcessRule;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProjectProcessRuleController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request, $projectId)
    {
        $project = Project::findOrFail($projectId);

        if (!auth()->user()->projects->contains($project)) {
            return response()->json([
                'status_code' => 2,
                'message' => 'Forbidden'
            ], 403);
        }

        $rules = $project->processRules()
            ->when($request->input('is_active', true), function ($query, $isActive) {
                return $query->where('is_active', $isActive);
            })
            ->orderBy('priority', 'desc')
            ->orderBy('match_type')
            ->paginate(10);

        return response()->json([
            'status_code' => 1,
            'data' => ProjectProcessRuleResource::collection($rules),
            'pagination' => [
                'current_page' => $rules->currentPage(),
                'last_page' => $rules->lastPage(),
                'per_page' => $rules->perPage(),
                'total' => $rules->total(),
            ],
            'message' => 'Project process rules fetched successfully',
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */

    public function store(Request $request, $projectId)
    {
        $project = Project::findOrFail($projectId);
        if (!auth()->user()->projects->contains($project)) {
            return response()->json(['status_code' => 2, 'message' => 'Forbidden'], 403);
        }

        $validated = $request->validate([
            'process' => 'required|string|max:255',
            'match_type' => ['required', Rule::in(['exact', 'domain', 'contains'])],
            'priority' => 'boolean',
            'is_active' => 'boolean',
        ]);

        $existingRule = $project->processRules()
            ->where('process', $validated['process'])
            ->first();

        if ($existingRule) {
            return response()->json([
                'status_code' => 2,
                'message' => 'A rule with the same process and match type already exists for this project.',
            ], 409);
        }

        $data = array_merge($validated, [
            'priority' => $request->input('priority', true),
            'is_active' => $request->input('is_active', true),
        ]);

        $rule = $project->processRules()->create($data);

        return response()->json([
            'status_code' => 1,
            'data' => new ProjectProcessRuleResource($rule),
            'message' => 'Project process rule created successfully',
        ], 201);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        $rule = ProjectProcessRule::findOrFail($id);
        $project = $rule->project;

        if (!auth()->user()->projects->contains($project)) {
            return response()->json(['status_code' => 2, 'message' => 'Forbidden'], 403);
        }

        $validated = $request->validate([
            'process' => 'sometimes|required|string|max:255',
            'match_type' => ['sometimes', 'required', Rule::in(['exact', 'domain', 'contains'])],
            'priority' => 'sometimes|required|boolean',
            'is_active' => 'sometimes|required|boolean',
        ]);

        $rule->update($validated);

        return response()->json([
            'status_code' => 1,
            'data' => new ProjectProcessRuleResource($rule),
            'message' => 'Project process rule updated successfully',
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        $rule = ProjectProcessRule::findOrFail($id);
        $project = $rule->project;

        if (!auth()->user()->projects->contains($project)) {
            return response()->json(['status_code' => 2, 'message' => 'Forbidden'], 403);
        }

        $rule->delete();

        return response()->json([
            'status_code' => 1,
            'message' => 'Project process rule deleted successfully',
        ], 200);
    }
}