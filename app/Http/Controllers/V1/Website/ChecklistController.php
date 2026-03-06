<?php

namespace App\Http\Controllers\V1\Website;

use App\Http\Controllers\Controller;
use App\Models\Checklist;
use App\Models\Task;
use App\Services\ChatGptService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class ChecklistController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @param \App\Models\Task $task
     * @return \Illuminate\Http\Response
     */
    public function index(Task $task)
    {
        return response()->json([
            'status_code' => 1,
            'data' => $task->checklists()->with('createdBy')->get(),
            'message' => 'Checklists fetched successfully',
        ]);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param \App\Models\Task $task
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request, Task $task)
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json(['status_code' => 0, 'errors' => $validator->errors()], 422);
        }

        $checklist = $task->checklists()->create([
            'title' => $request->title,
            'created_by' => auth()->user()->id,
        ]);

        return response()->json([
            'status_code' => 1,
            'data' => $checklist,
            'message' => 'Checklist created successfully',
        ], 201);
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\Checklist  $checklist
     * @return \Illuminate\Http\Response
     */
    public function show(Checklist $checklist)
    {
        return response()->json([
            'status_code' => 1,
            'data' => $checklist->load('createdBy'),
            'message' => 'Checklist fetched successfully',
        ]);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Checklist  $checklist
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, Checklist $checklist)
    {
        $validator = Validator::make($request->all(), [
            'title' => 'sometimes|string|max:255',
            'status' => 'sometimes|in:TODO,DONE',
        ]);

        if ($validator->fails()) {
            return response()->json(['status_code' => 0, 'errors' => $validator->errors()], 422);
        }

        $checklist->update($request->only(['title', 'status']));

        return response()->json([
            'status_code' => 1,
            'data' => $checklist,
            'message' => 'Checklist updated successfully',
        ]);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\Checklist  $checklist
     * @return \Illuminate\Http\Response
     */
    public function destroy(Checklist $checklist)
    {
        $checklist->delete();

        return response()->json([
            'status_code' => 1,
            'message' => 'Checklist deleted successfully',
        ], 200);
    }

    public function generateChecklist(Request $request, Task $task, ChatGptService $chatGptService)
    {
        $validator = Validator::make($request->all(), [
            'prompt' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['status_code' => 0, 'errors' => $validator->errors()], 422);
        }

        $userPrompt = $request->input('prompt');
        $fullPrompt = "Based on the following task, generate a checklist. The task name is: '{$task->name}' and the task description is: '{$task->description}'. The user's instruction is: '{$userPrompt}'. Please return a JSON array of strings, where each string is a checklist item. For example: [\"First item\", \"Second item\"].";

        try {
            $response = $chatGptService->askChatGpt($fullPrompt);
            $checklistItems = json_decode($response, true);

            if (json_last_error() !== JSON_ERROR_NONE || !is_array($checklistItems)) {
                Log::error('Invalid JSON response from ChatGPT for checklist generation', ['response' => $response]);
                $checklistItems = array_map('trim', explode("\n", $response));
                $checklistItems = array_filter($checklistItems, function($item) {
                    $item = preg_replace('/^[\-\*\d\.]+\s*/', '', $item);
                    return !empty($item);
                });
            }
            
            if (empty($checklistItems)) {
                 return response()->json([
                    'status_code' => 2,
                    'message' => 'Could not generate a checklist from the provided prompt.',
                ], 400);
            }

            $createdChecklists = [];
            foreach ($checklistItems as $item) {
                $createdChecklists[] = $task->checklists()->create([
                    'title' => $item,
                    'created_by' => auth()->user()->id,
                ]);
            }

            return response()->json([
                'status_code' => 1,
                'data' => $createdChecklists,
                'message' => 'Checklist generated and created successfully',
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'status_code' => 2,
                'message' => 'Failed to generate checklist. Please try again later.',
            ], 500);
        }
    }
}
