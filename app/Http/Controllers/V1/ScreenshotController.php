<?php

namespace App\Http\Controllers\V1;

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
            'ScreenshotImage' => 'required|mimes:jpeg,jpg,png|max:2048',
            'ProcessName' => 'required|string'
        ]);
        $file = $request->file('ScreenshotImage');
        $dir = '/uploads/screenshots/';

        $path = Helper::saveImageToServer($file,$dir);
        $process = Process::firstOrCreate([
            'process_name' => $request->input('ProcessName'), 
            'type' => 'BROWSER'
        ]);

        UserScreenshot::create([
            'process_id' => $process->id,
            'screenshot_path' => $path,
        ]);

        $response = [
            'StatusCode' => 1,
            'Data' => [],
            'Message' => 'Hurray !' 
        ];
        return response()->json($response);
    }
}

?>
