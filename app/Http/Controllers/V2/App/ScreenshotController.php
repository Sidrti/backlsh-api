<?php

namespace App\Http\Controllers\V2\App;

use App\Helpers\Helper;
use App\Http\Controllers\Controller;
use App\Models\Process;
use App\Models\UserScreenshot;
use File;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ScreenshotController extends Controller
{
    public function createScreenshot(Request $request)
    {
        $request->validate([
            'screenshot_image' => 'required|mimes:jpeg,jpg,png|max:2048',
            'process_name' => 'required|string',
            'project_id' => [ // Add validation for project_id
                'nullable',
                'integer',
                Rule::exists('projects', 'id'), // Ensure project exists if ID is given
            ],
            'task_id' => [ // Add validation for task_id
                'nullable',
                'integer',
                Rule::exists('tasks', 'id'), // Ensure task exists if ID is given
            ],
        ]);
        $file = $request->file('screenshot_image');
        $dir = '/uploads/screenshots';

        $path = Helper::saveImageToServer($file,$dir,false);

        $processName = strtolower(trim(pathinfo($request->input('process_name'), PATHINFO_FILENAME)));
      
        $type = Helper::computeType($processName);
        $process = Process::firstOrCreate([
            'process_name' => $processName, 
            'type' => $type
        ]);

        $websiteUrl = $type == 'BROWSER' ? Helper::getDomainFromUrl($request->input('website_url','')) : '';

        UserScreenshot::create([
            'process_id' => $process->id,
            'screenshot_path' => $path,
            'user_id' => auth()->user()->id,
            'website_url' => $websiteUrl,
            'project_id' => $request->input('project_id'), // Store project_id if provided
            'task_id' => $request->input('task_id'), // Store task_id if
        ]);

        $response = [
            'status_code' => 1,
            'message' => 'Hurray !' 
        ];
        return response()->json($response);
    }
}

?>
