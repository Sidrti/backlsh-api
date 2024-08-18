<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class UserScreenshot extends Authenticatable
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'process_id',
        'screenshot_path',
        'user_id',
        'website_url'
    ];

    public function getScreenshotPathAttribute($value)
    {
        if (filter_var($value, FILTER_VALIDATE_URL)) {
            return $value; // It's already a full URL, so return it as is
        }
        return $value != null ? config('app.asset_url').$value : $value;
    }
}
