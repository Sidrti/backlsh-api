<?php

use App\Http\Controllers\V1\App\ScreenshotController;
use App\Http\Controllers\V1\App\UserActivityController;
use App\Http\Controllers\V1\AuthController;
use App\Http\Controllers\v1\Website\AttendanceController;
use App\Http\Controllers\v1\Website\DashboardController;
use App\Http\Controllers\v1\Website\RealtimeController;
use App\Http\Controllers\v1\Website\ReportController;
use App\Http\Controllers\v1\Website\TeamController;
use App\Http\Controllers\V1\Website\UserProcessRatingController;
use App\Http\Controllers\v1\Website\WebsiteScreenshotController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;


Route::prefix('v1')->group(function () { 
    Route::group(['prefix' => 'website'], function () { 
        Route::post('/auth/login', [AuthController::class, 'login']);
        Route::post('/auth/register-admin', [AuthController::class, 'registerAdmin']);
        Route::get('/auth/google', [AuthController::class, 'redirectToGoogle']);
        Route::get('/auth/google/callback', [AuthController::class, 'handleGoogleCallback']);
        Route::get('/auth/linkedin', [AuthController::class, 'redirectToLinkedin']);
        Route::get('/auth/linkedin/callback', [AuthController::class, 'handleLinkedinCallback']);
        Route::get('/auth/verify-email/{verificationToken}/{user_id}', [AuthController::class, 'verifyEmail']);
        Route::post('/auth/forget-password/send-verification-link', [AuthController::class, 'forgetPasswordSendVerificationLink']);
        Route::get('/auth/forget-password/verify-email/{verificationToken}/{user_id}', [AuthController::class, 'forgetPasswordVerifyEmail']);
    });

    Route::group(['prefix' => 'app'], function () {  
        Route::post('/auth/login', [AuthController::class, 'login']);
        Route::post('/auth/google', [AuthController::class, 'verifyGoogleDesktopResponse']);
        Route::get('/fetch-total-time', [UserActivityController::class, 'getTotalTimeWorked']);
    });
    
    Route::group(['middleware' => ['auth:sanctum']], function () {
        Route::group(['prefix' => 'app'], function () { 
            Route::get('/user-activity/fetch-time', [UserActivityController::class, 'fetchUserTotalTimeInSeconds']);
            Route::post('/user-activity/create', [UserActivityController::class, 'createUserActivity']);

            Route::post('/screenshot/create',[ScreenshotController::class,'createScreenshot']);
        });
        
        Route::group(['prefix' => 'website'], function () {
            Route::get('/auth/user', [AuthController::class, 'me']);
            Route::post('/auth/user/update', [AuthController::class, 'meUpdate']);
            Route::get('/auth/account-details', [AuthController::class, 'fetchUserAccountDetails']);
            Route::post('/auth/forget-password/change-password', [AuthController::class, 'forgetPasswordChangePassword']);
            Route::get('/auth/send-verification-link', [AuthController::class, 'sendVerificationLink']);

            Route::get('/admin-dashboard', [DashboardController::class, 'fetchAdminDashboard']);
            Route::get('/user-dashboard', [DashboardController::class, 'fetchUserDashboard']);

            Route::get('/report', [ReportController::class, 'fetchUserReportsByDate']);
            Route::get('/report/sub-activity', [ReportController::class, 'fetchUserSubActivities']);

            Route::get('/screenshots', [WebsiteScreenshotController::class, 'fetchScreenshotsByUser']);

            Route::get('/realtime', [RealtimeController::class, 'fetchRealtimeUpdates']);

            Route::get('/team-member', [TeamController::class, 'fetchTeamMembers']);
            Route::post('/team-member/create',[TeamController::class,'createTeamMember']);
            Route::post('/team-member/bulk-create',[TeamController::class,'createTeamMemberBulkAdd']);
            Route::get('/team-member/sample-file-url', [TeamController::class, 'fetchSampleCsvUrl']);

            Route::get('/attendance',[AttendanceController::class,'fetchAttendance']);
            Route::post('/attendance/create',[AttendanceController::class,'createAttendanceSchedule']);

            Route::get('/productivity-ratings',[UserProcessRatingController::class,'fetchProcessRating']);
            Route::post('/productivity-ratings/create',[UserProcessRatingController::class,'createProcessRating']);
            
        });
    });
});

?>