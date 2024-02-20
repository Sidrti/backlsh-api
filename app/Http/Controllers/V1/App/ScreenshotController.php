<?php

namespace App\Http\Controllers\V1\App;

use App\Helpers\Helper;
use App\Http\Controllers\Controller;
use App\Models\Process;
use App\Models\UserScreenshot;
use File;
use Illuminate\Http\Request;

class ScreenshotController extends Controller
{
    public function createScreenshot(Request $request)
    {
        $request->validate([
            'screenshot_image' => 'required|mimes:jpeg,jpg,png|max:2048',
            'process_name' => 'required|string'
        ]);
        $file = $request->file('screenshot_image');
        $dir = '/uploads/screenshots/';

        $path = Helper::saveImageToServer($file,$dir);

        $type = Helper::computeType($request->input('process_name'));
        $process = Process::firstOrCreate([
            'process_name' => $request->input('process_name'), 
            'type' => $type
        ]);

        $websiteUrl = $type == 'BROWSER' ? Helper::getDomainFromUrl($request->input('website_url','')) : '';

        UserScreenshot::create([
            'process_id' => $process->id,
            'screenshot_path' => $path,
            'user_id' => auth()->user()->id,
            'website_url' => $websiteUrl
        ]);

        $response = [
            'status_code' => 1,
            'message' => 'Hurray !' 
        ];
        return response()->json($response);
    }
}

?>
