<?php

namespace App\Http\Controllers\V1\Website;

use App\Helpers\Helper;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\PayPalSubscriptions;
use Carbon\Carbon;
use Illuminate\Support\Facades\Response;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
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
            'role' => 'nullable|string|in:MEMBER,SUBADMIN,member,subadmin'
        ]);

        $user = User::where('email', $request->input('email'))->first();

        if (!$user) {
            $roleInput = strtoupper($request->input('role', 'MEMBER'));
            $role = in_array($roleInput, ['MEMBER', 'SUBADMIN']) ? $roleInput : 'MEMBER';

            return $this->inviteNewTeamMember($request->input('name'), $request->input('email'), $role);
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
                        $this->inviteNewTeamMember($name, $email, 'MEMBER');
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
        $adminId = auth()->user()->getAdminId();
        $tenDaysAgo = $currentDate->copy()->subDays(10)->toDateString();

        // Fetch team members along with their activity status
        $teamMembers = User::select('users.id','users.name','users.email','users.profile_picture','users.stealth_mode','users.role')
            ->leftJoin('user_activities', 'users.id', '=', 'user_activities.user_id')
             ->where(function ($query) use ($adminId) {
                $query->where('users.parent_user_id', $adminId)
                      ->orWhere('users.id', $adminId);
                })
            ->groupBy('users.id','users.name','users.email','users.profile_picture','users.stealth_mode','users.role')
            ->selectRaw('IF(MAX(user_activities.start_datetime) IS NULL OR MAX(user_activities.start_datetime) < ?, "INACTIVE", "ACTIVE") as activity_status', [$tenDaysAgo])
            ->get();

        $teamUserIds = $teamMembers->pluck('id');

        if($teamUserIds->count() <= 1 && !Helper::hasUsedBacklsh($teamUserIds)) {
            $demoMember = (object) config('dummy.team_member')[0];
            $teamMembers = collect([$demoMember])->merge($teamMembers);
        }

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
        $filePath = storage_path('app/public/uploads/default/sample.csv');
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
        $adminId = auth()->user()->getAdminId();

        if($user->parent_user_id == $adminId || $user->id == $adminId || $user->id == auth()->user()->id) {
            $user->stealth_mode = $request->input('stealth_mode');
            $user->save();

            return response()->json(['status_code'=> 1,'message' => 'Stealth mode updated successfully.'], 200);
        }
        return response()->json(['status_code'=> 2,'message' => 'Stealth mode not updated'], 200);
    }

    public function updateTeamMemberRole(Request $request)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'role' => 'required|string|in:MEMBER,SUBADMIN,member,subadmin',
        ]);

        $user = User::findOrFail($request->input('user_id'));
        $currentUser = auth()->user();
        $adminId = $currentUser->getAdminId();

        // The primary admin role cannot be modified
        if ($user->isAdmin() || $user->id == $adminId) {
            return response()->json(['status_code' => 2, 'message' => 'The primary admin role cannot be modified.'], 403);
        }

        // Only Admin or Subadmin can update roles
        if (!$currentUser->isAdminOrSubAdmin()) {
            return response()->json(['status_code' => 2, 'message' => 'You do not have permission to update member roles.'], 403);
        }

        // User cannot change their own role
        if ($user->id == $currentUser->id) {
            return response()->json(['status_code' => 2, 'message' => 'You cannot change your own role.'], 403);
        }

        // Must belong to the current team
        if ($user->parent_user_id != $adminId) {
            return response()->json(['status_code' => 2, 'message' => 'User does not belong to your team.'], 403);
        }

        $user->role = strtoupper($request->input('role'));
        $user->save();

        return response()->json([
            'status_code' => 1,
            'message' => 'Role updated successfully.',
            'data' => [
                'id' => $user->id,
                'role' => $user->role,
            ],
        ], 200);
    }

    public function deleteTeamMember($userId)
    {
        // Find the user
        $user = User::findOrFail($userId);
        $currentUser = auth()->user();
        $adminId = $currentUser->getAdminId();

        // Cannot delete primary admin
        if ($user->isAdmin() || $user->id == $adminId) {
            return response()->json(['status_code'=> 2,'message' => 'The primary admin cannot be deleted.'], 403);
        }

        // Only Admin or Subadmin can delete team members
        if (!$currentUser->isAdminOrSubAdmin()) {
            return response()->json(['status_code'=> 2,'message' => 'You do not have permission to delete members.'], 403);
        }

        // Subadmin cannot delete other Subadmins or Admin
        if ($currentUser->isSubAdmin() && ($user->isSubAdmin() || $user->isAdmin())) {
            return response()->json(['status_code'=> 2,'message' => 'Subadmins cannot delete other subadmins or admins.'], 403);
        }

        // Check if the user belongs to the current organization/admin
        if($user->parent_user_id == $adminId) {
            DB::transaction(function () use ($user, $adminId) {
                // 1. Detach many-to-many relationships
                $user->attendanceSchedules()->detach();
                $user->projects()->detach();

                // 2. Reassign reported issues to the admin
                DB::table('issues')->where('reported_by', $user->id)->update(['reported_by' => $adminId]);

                // 3. Delete productivity ratings and screenshots
                DB::table('productivity_ratings')->where('user_id', $user->id)->delete();
                DB::table('user_screenshots')->where('user_id', $user->id)->delete();

                // 4. Delete user activity dependencies (sub-activities first, then activities)
                DB::table('user_sub_activities')
                    ->whereIn('user_activity_id', function ($query) use ($user) {
                        $query->select('id')->from('user_activities')->where('user_id', $user->id);
                    })->delete();

                DB::table('user_activities')->where('user_id', $user->id)->delete();

                // 5. Delete the user
                $user->delete();
            });

            return response()->json(['status_code'=> 1,'message' => 'Member deleted successfully.'], 200);
        }

        return response()->json(['status_code'=> 2,'message' => 'You do not have permission to delete this member.'], 403);
    }

    private function inviteNewTeamMember($name, $email, $role = 'MEMBER')
    {
        $password = rand(100000, 9999999);
        $adminId = auth()->user()->getAdminId();
        Helper::createNewUser($name, $email, $password, 1, $role, $adminId);

        $data = [
            'name' => $name,
            'adminName' => auth()->user()->name,
            'email' => $email,
            'password' => $password
        ];

        $body = view('email.member_onboarding_email', $data)->render();
        $subject = auth()->user()->name .' has added you to their team';
        Helper::sendEmail($email, $subject, $body, $name);

        return response()->json([
            'status_code' => 1,
            'message' => 'Onboarding email has been sent to ' . $name,
        ]);
    }

    private function handleExistingTeamMember($user)
    {
        $adminId = auth()->user()->getAdminId();
        if ($user->parent_user_id == $adminId) {
            $message = $user->email . ' is already a member in your team';
        } else {
            $message = $user->email . ' is a member of another team. Kindly register another email for this member';
        }

        return response()->json([
            'status_code' => 2,
            'message' => $message,
        ]);
    }
}
