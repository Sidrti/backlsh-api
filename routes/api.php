<?php

use App\Http\Controllers\V1\UserActivityController;
use App\Http\Controllers\V1\AuthController;
use App\Http\Controllers\V1\ScreenshotController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;


Route::prefix('v1')->group(function () { 

    Route::post('/auth/login', [AuthController::class, 'login']);

    Route::get('/auth/google', [AuthController::class, 'redirectToGoogle']);
    Route::get('/auth/google/callback', [AuthController::class, 'handleGoogleCallback']);

    Route::get('/auth/linkedin', [AuthController::class, 'redirectToLinkedin']);
    Route::get('/auth/linkedin/callback', [AuthController::class, 'handleLinkedinCallback']);

    Route::post('/auth/desktop/google', [AuthController::class, 'verifyGoogleDesktopResponse']);
    
    Route::group(['middleware' => ['auth:sanctum']], function () {
        Route::get('/user',function (Request $request) {
            return $request->user();
        });
        Route::post('/user-activity/create', [UserActivityController::class, 'createUserActivity']);
        
        Route::post('/screenshot/create',[ScreenshotController::class,'createScreenshot']);
            
    });

});

