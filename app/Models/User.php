<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;

use App\AttendanceSchedule;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;
use Laravel\Cashier\Billable;
use Laravel\Sanctum\HasApiTokens;
use TCG\Voyager\Models\Role;

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
        'verification_token'

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
    ];

    public function attendanceSchedules()
    {
      return $this->belongsToMany(AttendanceSchedule::class);
    }
    // public function getProfilePictureAttribute($value)
    // {
    //     if (filter_var($value, FILTER_VALIDATE_URL)) {
    //         return $value; // It's already a full URL, so return it as is
    //     }
    //     return $value != null ? config('app.asset_url').$value : $value;
    // }
    public function getProfilePictureAttribute($value)
    {
        if (!$value) {
            return null;
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
    public function isPayPalSubscribed()
    {
        $today = Carbon::today();
        $subscription = $this->subscriptions()
            ->where('stripe_status', 'ACTIVE')
            ->where('ends_at', '>', $today)
            ->first();
        return $subscription !== null;
    }

}
