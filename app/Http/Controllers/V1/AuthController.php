<?php
namespace App\Http\Controllers\V1;

use App\Helpers\Helper;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserActivity;
use Exception;
use GuzzleHttp\Psr7\Response;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Laravel\Socialite\Facades\Socialite;
use Illuminate\Support\Str;
use App\Rules\Turnstile;
use App\Services\VerificationEmailService;

class AuthController extends Controller
{
    public function login(Request $request, VerificationEmailService $verificationEmail) {

        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $user = User::where('email', $request->email)->first();
        if($user) {
            if (Hash::check($request->input('password'), $user->password)) {
                if (!$user->is_verified) {
                    $sent = $verificationEmail->send($user);
                    return response()->json([
                        'status_code' => 2,
                        'data' => [
                            'is_verified' => false,
                            'email' => $user->email,
                        ],
                        'message' => $sent
                            ? 'Please verify your email address before logging in. A verification link has been sent to your email. Please check your inbox.'
                            : 'Please verify your email address before logging in. We could not send the verification email. Please try resending the verification link.'
                    ]);
                }

                $token = $user->createToken('api-token')->plainTextToken;
                $user->append('profile_picture');
                return response()->json(['status_code' => 1,'data' => ['user' => $user, 'token' => $token ],'message'=>'Login successfull.']);
            }
            else {
                return response()->json(['status_code' => 2, 'data' => [], 'message'=>'Incorrect password.']);
            }
        }
        else {
            return response()->json(['status_code' => 2, 'data' => [], 'message'=>'Account not registered']);
        }
    }
    public function registerAdmin(Request $request)
    {
        if (!$request->has('turnstile_token') && $request->has('cf-turnstile-response')) {
            $request->merge(['turnstile_token' => $request->input('cf-turnstile-response')]);
        }

        $request->validate([
            'name' => 'required',
            'email' => 'required|email',
            'password' => 'required|min:6',
            'mobile' => 'required|string|min:6|max:15',
            'country_code' => 'required|string',
            'turnstile_token' => ['required', 'string', new Turnstile($request->ip())],
        ], [
            'turnstile_token.required' => 'Please complete the security check.',
        ]);

        $user = User::where('email', $request->input('email'))->first();
        if(!$user) {
            $user = Helper::createNewUser(
                $request->input('name'),
                $request->input('email'),
                $request->input('password'),
                1,
                'ADMIN',
                0,
                $request->input('mobile'),
                $request->input('country_code')
            );

            $verificationLink = $this->generateVerificationLink($user->id, 'REGISTER');
            $data = [
                'name' => $user->name,
                'link' => $verificationLink
            ];
            $body = view('email.verification_email', $data)->render();
            $subject = 'Verify your email';
            Helper::sendEmail($user->email, $subject, $body, $user->name);

            $response = [
                'status_code' => 1,
                'data' => [
                    'is_verified' => false,
                    'email' => $user->email,
                ],
                "message" => 'Your account has been created! Please check your email to verify your account before logging in.',
            ];
            return response()->json($response);
        }
        else {
            $message = 'Your account already exists ! Please login ';
            $data = [
                'status_code' => 2,
                "message" => $message,
            ];
            return response()->json($data);
        }
    }
    public function resendRegistrationVerificationLink(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
        ]);

        $user = User::where('email', $request->input('email'))->first();
        if ($user) {
            if ($user->is_verified) {
                return response()->json([
                    'status_code' => 2,
                    'message' => 'This account is already verified. Please log in.',
                ]);
            }

            $verificationLink = $this->generateVerificationLink($user->id, 'REGISTER');
            $data = [
                'name' => $user->name,
                'link' => $verificationLink
            ];
            $body = view('email.verification_email', $data)->render();
            $subject = 'Verify your email';
            Helper::sendEmail($user->email, $subject, $body, $user->name);

            return response()->json([
                'status_code' => 1,
                'message' => 'Verification link has been resent! Please check your email inbox.',
            ]);
        }

        return response()->json([
            'status_code' => 2,
            'message' => 'Account not found with this email address.',
        ]);
    }
    public function sendVerificationLink()
    {
        $verificationLink =$this->generateVerificationLink(auth()->user()->id,'REGISTER');
        $user = auth()->user();
        $data = [
            'name' => $user->name,
            'link' => $verificationLink
        ];
        $body = view('email.verification_email', $data)->render();
        $subject = 'Verify your email';
        Helper::sendEmail($user->email,$subject,$body,$user->name);
        $response = [
            'status_code' => 1,
            'message' => 'Verication link sent',
            'link' => $verificationLink
        ];
        return response()->json($response);

    }
    public function forgetPasswordSendVerificationLink(Request $request)
    {
        $request->validate(['email' => 'required|email']);
        $user = User::where('email', $request->input('email'))->first();
        if (!$user) {
            return response()->json(['status_code' => 2, 'message' => 'Account not found with this email address.']);
        }

        $broker = Password::broker();
        if ($broker->getRepository()->recentlyCreatedToken($user)) {
            return response()->json(['status_code' => 2, 'message' => 'Please wait a minute before requesting another reset link.']);
        }
        $token = $broker->createToken($user);
        $link = rtrim(config('app.website_url'), '/') . '/change-password?' . http_build_query([
            'token' => $token,
            'email' => $user->email,
        ]);
        $body = view('email.reset_password', ['name' => $user->name, 'link' => $link,
            'minutes' => config('auth.passwords.users.expire', 60)])->render();
        if (Helper::sendEmail($user->email, 'Reset your Backlsh password', $body, $user->name) !== true) {
            $broker->deleteToken($user);
            return response()->json(['status_code' => 2, 'message' => 'Unable to send the reset email. Please try again.'], 503);
        }
        return response()->json(['status_code' => 1, 'message' => 'Password reset link sent. Please check your email.']);
    }

    public function forgetPasswordChangePassword(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'token' => 'required|string',
            'password' => 'required|string|min:6|confirmed',
        ]);
        $status = Password::broker()->reset(
            $request->only('email', 'token', 'password', 'password_confirmation'),
            function ($user, $password) {
                $user->password = Hash::make($password);
                $user->setRememberToken(Str::random(60));
                $user->save();
                $user->tokens()->delete();
            }
        );
        if ($status !== Password::PASSWORD_RESET) {
            return response()->json(['status_code' => 2,
                'message' => 'This password reset link is invalid or has expired. Please request a new one.'], 422);
        }
        return response()->json(['status_code' => 1, 'message' => 'Password updated. Please log in with your new password.']);
    }
    private function generateVerificationLink($user_id,$type='')
    {
        $verificationToken = Str::random(40);
        $user = User::where('id', $user_id)->first();
        $user->update([
            'verification_token' => $verificationToken,
        ]);

        $baseUrl = url('/');
        $verificationLink = $baseUrl . '/api/v1/website/auth/verify-email/' . $verificationToken.'/'.$user_id;

        return $verificationLink;
    }
    public function verifyEmail($verificationToken,$user_id)
    {
        $user = User::where('id', $user_id)->where('verification_token',$verificationToken)->first();
        if($user) {
            $user->update([
                'verification_token' => '',
                'is_verified' => 1
            ]);
            $redirectUrl = rtrim(config('app.website_url'), '/') . '/login?verified=1';
            return redirect($redirectUrl);
        }
        else {
            $data = [
                'status_code' => 2,
                'message' => 'Unable to verify email. Please try again',

            ];
            return response()->json($data);
        }
    }
    public function forgetPasswordVerifyEmail($verificationToken, $user_id)
    {
        // Old verification emails cannot be used as password reset credentials.
        return redirect(rtrim(config('app.website_url'), '/') . '/forget-password?expired=1');
    }
    public function redirectToGoogle()
    {
        return Socialite::driver('google')->stateless()->redirect();
    }
    public function redirectToLinkedin()
    {
        return Socialite::driver('linkedin-openid')->stateless()->redirect();
    }
    public function handleGoogleCallback()
    {
        try {
            $googleUser = Socialite::driver('google')->stateless()->user();

            $user = User::where('email', $googleUser->getEmail())->first();

            if (!$user) {
                $user = Helper::createNewUser($googleUser->getName(),$googleUser->getEmail(),rand(10000,1000000),2,'ADMIN');

                $message = 'Hurray '.$googleUser->getName().' ! Happy productivity.';
            }
            else {
                $user = User::where('email', $googleUser->getEmail())->first();
                $message = 'Welcome back '.$googleUser->getName();
            }
            $token = $user->createToken('api-token')->plainTextToken;
            return redirect(config('app.website_url').'/social-login?token=' . $token);
        }
        catch(Exception $e) {
            return redirect(config('app.website_url'));
        }
    }
    public function me(Request $request)
    {
        \App\Support\ReportTime::rememberBrowserTimezone(auth()->user(), $request->query('timezone'));
        $token =  $request->header('Authorization');
        if (Str::startsWith($token, 'Bearer ')) {
            $token = Str::substr($token, 7); // Remove the 'Bearer ' prefix
        }
       $subscription = (Helper::getUserSubscription(auth()->user()->id));

          $data = [
            'status_code' => 1,
            'data' => [
                'user' => auth()->user(),
                'token' => $token,
                'subscription' => $subscription
            ],
            "message" => 'User details fetched',

        ];
        return response()->json($data);
    }
    public function meUpdate(Request $request)
    {
        $request->validate([
            'media' => 'required|mimes:png,jpg,jpeg|max:5000',
        ]);
        $file = $request->file('media');
        $dir = '/uploads/profile';
        $path = Helper::saveImageToServer($file,$dir,true);
        $user = User::find(auth()->user()->id);
        $user->update([
            'profile_picture' => $path,
        ]);
        $data = [
            'status_code' => 1,
            "message" => 'Profile picture saved',

        ];
        return response()->json($data);
    }

    public function updateSettings(Request $request)
    {
        $request->validate([
            'timezone' => ['sometimes', 'required', 'timezone'],
            'report_time' => ['sometimes', 'required', 'date_format:H:i'],
        ]);
        $user = auth()->user();
        
        $settings = $user->settings ?? [];
        
        if ($request->has('report_time')) {
            $settings['report_time'] = $request->input('report_time');
        }
        
        if ($request->has('report_emails')) {
            $settings['report_emails'] = is_array($request->input('report_emails')) ? $request->input('report_emails') : array_map('trim', explode(',', $request->input('report_emails')));
        }
        
        if ($request->has('timezone')) {
            $settings['timezone'] = $request->input('timezone');
        }
        
        $user->settings = $settings;
        $user->save();
        
        return response()->json([
            'status_code' => 1,
            'message' => 'Settings updated successfully',
            'data' => $settings
        ]);
    }
    public function fetchUserAccountDetails()
    {
        $user = auth()->user();
        $adminId = $user->getAdminId();
        $subscription = Helper::getUserSubscription($adminId);
      	$ifSubscribed = $subscription['subscribed'] ? true : false;
        $userSteps = $this->getUserCompletedSteps($user, $ifSubscribed);
        $subscription['sub_title'] = $ifSubscribed? 'Premium Member' : 'left in your trial';
        $subscription['title'] = $ifSubscribed ? 'Subscribed' : $subscription['remaining_trial_days'].' Days';
        $subscription['show_button'] = $user->isAdmin() ? !$ifSubscribed : false;
        $subscription['button_text'] = 'UPGRADE NOW';
        $subscription['button_link'] = '/dashboard';
        $data = [
            'status_code' => 1,
            'data' => [
                'user_steps' => $userSteps,
                'subscription' => $subscription
            ],
            "message" => 'User details fetched',

        ];
        return response()->json($data);
    }
    public function handleLinkedinCallback()
    {
        $linkedinUser = Socialite::driver('linkedin-openid')->stateless()->user();

        $user = User::where('email', $linkedinUser->getEmail())->first();

        if (!$user) {
            $user = User::create([
                'name' => $linkedinUser->getName(),
                'email' => $linkedinUser->getEmail(),
                'password' => rand(10000,1000000),
                'login_type' => 3,
                'role' => 'ADMIN'
            ]);

            $message = 'Hurray '.$linkedinUser->getName().' ! Happy productivity.';
        }
        else {
            $user = User::where('email', $linkedinUser->getEmail())->first();
            $message = 'Welcome back '.$linkedinUser->getName();

        }
        $token = $user->createToken('api-token')->plainTextToken;
        return redirect(config('app.website_url').'/social-login?token=' . $token);
    }
    public function verifyGoogleDesktopResponse(Request $request)
    {
        $userinfoRequestUri = 'https://www.googleapis.com/oauth2/v3/userinfo';

        $userInfoResponse = Http::withHeaders([
            'Authorization' => 'Bearer ' . $request->access_token,
            'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
        ])->get($userinfoRequestUri);

        $userInfo = json_decode($userInfoResponse->body(), true);

        $email = $userInfo['email'];

        $user = User::where('email', $email)->first();

        if($user) {
            if($user->is_verified == 0) {
                $user->update(['is_verified' => 1]);
            }
            $token = $user->createToken('api-token')->plainTextToken;
            return response()->json(['status_code' => 1,'data' => ['user' => $user, 'token' => $token ],'message'=>'Login successfull.']);
        }
        else {
            return response()->json(['status_code' => 2, 'data' => [], 'message'=>'Account not registered']);
        }
    }
    public function fetchBacklshAppUrl(Request $request)
    {
        $os = 'windows';
        if($request->has('os') && $request->os == 'mac') {
            $os = 'mac';
            $filePath = config('app.mac_app_setup_link');
        } else {
            $filePath = config('app.app_setup_link');
        }

        try {
            \Illuminate\Support\Facades\DB::table('downloads')->insert([
                'os' => $os,
                'created_date_time' => now(),
                'updated_datetime' => now(),
            ]);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error("Failed to log download: " . $e->getMessage());
        }

        return response()->json(['status_code' => 1,'data' => ['url' => $filePath ]]);
    }

    private function getUserCompletedSteps($user,$subscribed=false)
    {
        $teamMemberExists = User::where('parent_user_id',$user->id)->exists();
        $activityCount = UserActivity::where('user_id', $user->id)->count();
        $totalSteps = 5;
        $createAccount = true;
        $completedSteps = (
            $createAccount +                             // Create Account
            ($user->is_verified ? 1 : 0) +  // Confirm Email
            ($activityCount > 0 ? 1 : 0) +  // Download App
            ($teamMemberExists ? 1 : 0) + // Add Team Member
            $subscribed                               // Add Card
        );

        // Calculate the percentage of steps completed
        $percentageCompleted = ($completedSteps / $totalSteps) * 100;
        return [
            'create_account' => $createAccount,
            'confirm_email' => $user->is_verified ? true : false,
            'download_app' => $activityCount > 0 ? true : false,
            'add_team_member' => $teamMemberExists,
            'add_card' => $subscribed,
            'percentage' => $percentageCompleted
        ];
    }
}
