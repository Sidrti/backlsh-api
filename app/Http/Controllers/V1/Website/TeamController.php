<?php

namespace App\Http\Controllers\V1\Website;

use App\Helpers\Helper;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\PayPalSubscriptions;
use Carbon\Carbon;
use Illuminate\Support\Facades\Response;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use League\Csv\Reader;

class TeamController extends Controller
{
    protected $paypalSubscriptionService;

    public function __construct(PayPalSubscriptions $paypalSubscriptionService)
    {
        $this->paypalSubscriptionService = $paypalSubscriptionService;
    }
    public function createTeamMember(Request $request)
    {
        $request->validate([
            'name' => 'required|string',
            'email' =>'required|email',
        ]);

        $user = User::where('email', $request->input('email'))->first();

        if (!$user) {
           // $parentUser = User::find(auth()->user()->id);
          //  $subscription = $parentUser->subscriptions()->first();
           // $teamMemberCount = User::where('parent_user_id',$parentUser->id)->count() + 1;
         //   $this->paypalSubscriptionService->updateQuantity($subscription->stripe_id,$teamMemberCount);
            return $this->inviteNewTeamMember($request->input('name'), $request->input('email'));
        } else {
            return $this->handleExistingTeamMember($user);
        }
    }
    public function createTeamMemberBulkAdd(Request $request)
    {
        $request->validate([
            'file' => 'required|mimes:csv|max:10240',
        ]);
        if ($request->hasFile('file')) {
            $file = $request->file('file');
            $reader = Reader::createFromPath($file->getPathname(), 'r');
            $reader->setHeaderOffset(0); 

            $memberNotInvitedCount = 0;
            $memberInvitedCount = 0;
            $memberDuplicateCount = 0;
            foreach ($reader as $row) {
                 $email = $row['EMAIL'];
                 $name = $row['NAME'];
                 if (isset($email) && isset($name) && filter_var($email, FILTER_VALIDATE_EMAIL) && $name != null) {
                    $user = User::where('email', $email)->first();
                    if (!$user) {
                        $this->inviteNewTeamMember($name,$email);
                        $memberInvitedCount++;
                    }
                    else {
                      $memberDuplicateCount++;  
                    }
                 }
                 else {
                    $memberNotInvitedCount++;
                 }
            }
            return response()->json(['status_code' => 1,'data' => ['member_invited_count' => $memberInvitedCount,'member_not_invited_count' => $memberNotInvitedCount,'member_duplicate_count' => $memberDuplicateCount ],'message' => 'Operation successfull'], 200);
        }
        else {
            return response()->json(['status_code' => 2,'message' => 'Invalid file'], 200);
        }

    }
    public function fetchTeamMembers(Request $request)
    {
        $currentDate = Carbon::now();
        $userId = auth()->user()->id;
        $tenDaysAgo = $currentDate->copy()->subDays(10)->toDateString();
    
        // Fetch team members along with their activity status
        $teamMembers = User::select('users.id','users.name','users.email','users.profile_picture','users.stealth_mode')
            ->leftJoin('user_activities', 'users.id', '=', 'user_activities.user_id')
             ->where(function ($query) use ($userId) {
                $query->where('users.parent_user_id', $userId)
                      ->orWhere('users.id', $userId);
                })
            ->groupBy('users.id','users.name','users.email','users.profile_picture','users.stealth_mode')
            ->selectRaw('IF(MAX(user_activities.start_datetime) IS NULL OR MAX(user_activities.start_datetime) < ?, "INACTIVE", "ACTIVE") as activity_status', [$tenDaysAgo])
            ->get();

        $totalMembersCount = $teamMembers->count();
        $activeMemberCount = 0;
        $inActiveMemberCount = 0;
        foreach($teamMembers as $item) {
            $item->activity_status == 'ACTIVE' ? $activeMemberCount++ : $inActiveMemberCount++;
        }
    
        return response()->json([
            'status_code' => 1,
            'data' => ['team' => $teamMembers,'total_member_count' => $totalMembersCount,'active_member_count' => $activeMemberCount,'inactive_member_count' =>$inActiveMemberCount ],
            'message' => 'Team members fetched',
        ]);
    }
    
    public function fetchSampleCsvUrl()
    {
        $filePath = storage_path('app/public/sample/sample.csv');
        return Response::download($filePath, 'sample.csv');
    }

    public function updateStealthMode(Request $request)
    {
        // Validate the request
        $request->validate([
            'stealth_mode' => 'required|boolean',
            'user_id' => 'required|exists:users,id',
        ]);

        // Find the user
        $user = User::findOrFail($request->input('user_id'));

        if($user->parent_user_id == auth()->user()->id || $user->id == auth()->user()->id) {
            $user->stealth_mode = $request->input('stealth_mode');
            $user->save();
    
            return response()->json(['status_code'=> 1,'message' => 'Stealth mode updated successfully.'], 200);
        }
        return response()->json(['status_code'=> 2,'message' => 'Stealth mode not updated'], 200);

    }

    private function inviteNewTeamMember($name, $email)
    {
        $password = rand(100000, 9999999);
        Helper::createNewUser($name, $email, $password, 1, 'MEMBER', auth()->user()->id);

        $data = [
            'name' => $name,
            'adminName' => auth()->user()->name,
            'email' => $email,
            'password' => $password
        ];

        $body = view('email.member_onboarding_email', $data)->render();
        $subject = auth()->user()->name .' has added you to their team';
        Helper::sendEmail($email, $subject, $body);
       
        return response()->json([
            'status_code' => 1,
            'message' => 'Onboarding email has been sent to ' . $name,
        ]);
    }

    private function handleExistingTeamMember($user)
    {
        if ($user->parent_user_id == auth()->user()->id) {
            $message = $user->email . ' is already a member in your team';
        } else {
            $message = $user->email . ' is a member of the admin. Kindly register another email for this member';
        }

        return response()->json([
            'status_code' => 2,
            'message' => $message,
        ]);
    }
}