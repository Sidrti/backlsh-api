<?php
namespace App\Http\Controllers\V1;

use App\Helpers\Helper;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserActivity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Laravel\Socialite\Facades\Socialite;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    public function login(Request $request) {

        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $user = User::where('email', $request->email)->first();
        if($user) {
            if ($user && Hash::check($request->input('password'), $user->password)) { 
                $token = $user->createToken('api-token')->plainTextToken;
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
        $request->validate([
            'name' => 'required',
            'email' => 'required|email',
            'password' => 'required|min:6',
        ]);

        $user = User::where('email', $request->input('email'))->first();
        if(!$user) {
            $user = Helper::createNewUser($request->input('name'),$request->input('email'),$request->input('password'),1,'ADMIN');
            $message = 'Your account has been created !';
            $token = $user->createToken('api-token')->plainTextToken;

            $verificationLink =$this->generateVerificationLink($user->id,'REGISTER');
            $data = [
                'name' => $user->name,
                'link' => $verificationLink
            ];
            $body = view('email.verification_email', $data)->render();
            $subject = 'Verify your email';
            Helper::sendEmail($user->email,$subject,$body);

            $response = [
                'status_code' => 1,
                'data' => [
                    'user' => $user,
                    'token' => $token,
                ],
                "message" => $message,
    
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
        Helper::sendEmail($user->email,$subject,$body);
        $response = [
            'status_code' => 1,
            'message' => 'Verication link sent',
            'link' => $verificationLink
        ];
        return response()->json($response);

    }
    public function forgetPasswordSendVerificationLink(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
        ]);

        $user = User::where('email',$request->input('email'))->first();
        if($user) {
            $verificationLink =$this->generateVerificationLink($user->id,'FORGET_PASSWORD');
            $data = [
                'name' => $user->name,
                'link' => $verificationLink
            ];
            $body = view('email.verification_email', $data)->render();
            $subject = 'Verify your email';
            Helper::sendEmail($user->email,$subject,$body);
            $response = [
                'status_code' => 1,
                'message' => 'Verication link sent. Please check your email',
                'link' => $verificationLink
            ];
        }
        else {
            $response = [
                'status_code' => 2,
                'message' => 'Incorrect email',
            ];
        }

        return response()->json($response);
    }
    public function forgetPasswordChangePassword(Request $request)
    {
        $request->validate([
            'password' => 'required|min:6',
        ]);
        $user = User::find(auth()->user()->id);
        $user->password = Hash::make($request->input('password'));
        $user->save();

        $token =  $request->header('Authorization');
        if (Str::startsWith($token, 'Bearer ')) {
            $token = Str::substr($token, 7); // Remove the 'Bearer ' prefix
        }
        $response = [
            'status_code' => 1,
            'data' => [
                'user' => $user,
                'token' => $token,
            ],
            'message' => 'Password updated',
        ];
        return response()->json($response);

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
        if($type == 'FORGET_PASSWORD') {
            $verificationLink = $baseUrl . '/api/v1/website/auth/forget-password/verify-email/' . $verificationToken.'/'.$user_id;
        }
        
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
            return redirect(config('app.website_url'));
        }
        else {
            $data = [
                'status_code' => 2,
                'message' => 'Unable to verify email. Please try again',
    
            ];
            return response()->json($data);
        }
    }
    public function forgetPasswordVerifyEmail($verificationToken,$user_id)
    {
        $user = User::where('id', $user_id)->where('verification_token',$verificationToken)->first();
        if($user) {
            $user->update([
                'verification_token' => '',
            ]);
            $token = $user->createToken('api-token')->plainTextToken;
            $redirectWebsiteUrl = config('app.website_url').'/change-password?token='.$token;
            return redirect($redirectWebsiteUrl);
        }
        else {
            $data = [
                'status_code' => 2,
                'message' => 'Unable to verify email. Please try again',
    
            ];
            return response()->json($data);
        }
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

        // $data = [
        //     'status_code' => 1,
        //     'data' => [
        //         'user' => $user,
        //         'token' => $token,
        //     ],
        //     "message" => $message,

        // ];
        // return response()->json($data);
    }
    public function me(Request $request)
    {
        $token =  $request->header('Authorization');
        if (Str::startsWith($token, 'Bearer ')) {
            $token = Str::substr($token, 7); // Remove the 'Bearer ' prefix
        }
        
          $data = [
            'status_code' => 1,
            'data' => [
                'user' => auth()->user(),
                'token' => $token,
            ],
            "message" => 'User details fetched',

        ];
        return response()->json($data);
    }
    public function fetchUserAccountDetails() 
    {
        $user = auth()->user();
        $userSteps = $this->getUserCompletedSteps($user);
        $subscription = [
            'days_left' => 9,
            'sub_title' => 'left in your free trial',
            'show_button' => true,
            'button_text' => 'UPGRADE NOW',
            'button_link' => '/dashboard'
        ];
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
            'Authorization' => 'Bearer ' . $request->AcessToken,
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
    private function getUserCompletedSteps($user)
    {
        $team_member_exists = User::where('parent_user_id',$user->id)->exists();
        $activityCount = UserActivity::where('user_id', $user->id)
        ->count();

        return [
            'create_account' => true,
            'confirm_email' => $user->is_verified,
            'download_app' => $activityCount > 0 ? true : false,
            'add_team_member' => $team_member_exists,
            'add_card' => true,
        ];
    }
}
