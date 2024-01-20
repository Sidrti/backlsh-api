<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserSubActivity extends Model
{
    use HasFactory;

    protected $fillable = ['user_activity_id', 'title','website_url', 'start_datetime', 'end_datetime', 'productivity_status'];
}
