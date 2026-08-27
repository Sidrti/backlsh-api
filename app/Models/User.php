<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;

use App\Models\AttendanceSchedule;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;
use Laravel\Cashier\Billable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, Billable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'profile_picture',
        'login_type',
        'role',
        'parent_user_id',
        'status',
        'trial_ends_at',
        'pm_type',
        'pm_last_four',
        'is_verified',
        'verification_token',
        'teams_webhook_url',
        'mobile',
        'country_code'
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'updated_at',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'trial_ends_at' => 'datetime:Y-m-d',
        'settings' => 'array',
    ];

    public function attendanceSchedules()
    {
      return $this->belongsToMany(AttendanceSchedule::class);
    }
    public function getProfilePictureAttribute($value)
    {
        if (!$value) {
            return asset('storage/' . (config(('app.user_default_image'))));
        }

        if (filter_var($value, FILTER_VALIDATE_URL)) {
            // It's already a full URL (like DiceBear), return as-is
            return $value;
        }

        // Otherwise, treat it as a relative path stored in local storage
        return config('app.asset_url') . Storage::url($value);
    }

    public function userActivities()
    {
        return $this->hasMany(UserActivity::class);
    }

    public function createdProjects()
    {
        return $this->hasMany(Project::class, 'created_by');
    }

    public function projects()
    {
        return $this->belongsToMany(Project::class, 'project_members');
    }

    public function assignedTasks()
    {
        return $this->hasMany(Task::class, 'assignee_id');
    }

    public function notifications()
    {
        return $this->hasMany(Notification::class);
    }

    public function unreadNotifications()
    {
        return $this->hasMany(Notification::class)->where('is_read', false);
    }

    public function isPayPalSubscribed()
    {
        $today = Carbon::today();
        $subscription = $this->subscriptions()
            ->where('stripe_status', 'ACTIVE')
            ->where('ends_at', '>', $today)
            ->first();
        return $subscription !== null;
    }

    public function subUsers()
    {
        return $this->hasMany(User::class, 'parent_user_id');
    }

    public function latestActivity()
    {
        return $this->hasOne(UserActivity::class)->latestOfMany('start_datetime');
    }

    public static function getInstallsData()
    {
        $results = \Illuminate\Support\Facades\DB::table('user_activities as ua')
            ->join('processes as p', 'p.id', '=', 'ua.process_id')
            ->select(
                'ua.user_id', 
                \Illuminate\Support\Facades\DB::raw('MIN(ua.start_datetime) as installed_at'),
                \Illuminate\Support\Facades\DB::raw("IF(SUM(CASE WHEN p.process_name LIKE '%.exe' OR p.process_name IN ('explorer', 'lockapp', 'taskmgr', 'winword', 'windowsterminal', 'notepad') THEN 1 ELSE 0 END) > 0, 'windows', 'mac') as platform")
            )
            ->where('p.type', 'APPLICATION')
            ->groupBy('ua.user_id')
            ->get();

        return collect($results)->map(function ($row) {
            return (object) [
                'user_id' => $row->user_id,
                'installed_at' => \Carbon\Carbon::parse($row->installed_at),
                'platform' => $row->platform,
            ];
        });
    }
}
