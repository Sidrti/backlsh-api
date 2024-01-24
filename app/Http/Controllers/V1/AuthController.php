<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Laravel\Socialite\Facades\Socialite;

class AuthController extends Controller
{
    public function login(Request $request) {

        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $user = User::where('email', $request->email)->first();
        if($user) {
            //if($user->is_verified) {
                if ($user && Hash::check($request->input('password'), $user->password)) { 
                    $token = $user->createToken('api-token')->plainTextToken;
                    return response()->json(['status_code' => 1,'data' => ['user' => $user, 'token' => $token ],'message'=>'Login successfull.']);
                }
                else {
                    return response()->json(['status_code' => 2, 'data' => [], 'message'=>'Incorrect password.']);
                }
          //  }
            //else {
              //  return response()->json(['status_code' => 2, 'data' => [], 'message'=>'Account not verified. Please goto register first']);
            //}
        }
        else {
            return response()->json(['status_code' => 2, 'data' => [], 'message'=>'Account not registered']);
        }
    }
    public function redirectToGoogle()
    {
        return Socialite::driver('google')->stateless()->redirect();
    }
    public function redirectToLinkedin()
    {
        return Socialite::driver('linkedin')->stateless()->redirect();
    }
    public function handleGoogleCallback()
    {
        $googleUser = Socialite::driver('google')->stateless()->user();
        
        $user = User::where('email', $googleUser->getEmail())->first();

        if (!$user) {
            $user = User::create([
                'name' => $googleUser->getName(),
                'email' => $googleUser->getEmail(),
                'password' => rand(10000,1000000),
                'login_type' => 2,
                'role' => 'ADMIN'
            ]);

            $message = 'Hurray '.$googleUser->getName().' ! Happy productivity.';
        }
        else {
            $user = User::where('email', $googleUser->getEmail())->first();
            $message = 'Welcome back '.$googleUser->getName();
           
        }
        $token = $user->createToken('api-token')->plainTextToken;
        $data = [
            'status_code' => 1,
            'data' => [
                'user' => $user,
                'token' => $token,
            ],
            "message" => $message,

        ];
        return response()->json($data);
    }
    public function handleLinkedinCallback()
    {
        $linkedinUser = Socialite::driver('linkedin')->stateless()->user();
        
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
        $data = [
            'status_code' => 1,
            'data' => [
                'user' => $user,
                'token' => $token,
            ],
            "message" => $message,

        ];
        return response()->json($data);
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
    
}
