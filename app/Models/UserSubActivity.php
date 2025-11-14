<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserSubActivity extends Model
{
    use HasFactory;

    protected $fillable = ['user_activity_id', 'user_id', 'project_id', 'task_id', 'title','website_url', 'start_datetime', 'end_datetime', 'productivity_status','process_id'];

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function task()
    {
        return $this->belongsTo(Task::class);
    }
}
