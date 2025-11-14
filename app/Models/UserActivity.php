<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserActivity extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'project_id', 'task_id', 'process_id', 'start_datetime', 'end_datetime', 'productivity_status'];

    protected $casts = [
        'start_datetime' => 'datetime',
        'end_datetime' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function process()
    {
        return $this->belongsTo(Process::class, 'process_id');
    }

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function task()
    {
        return $this->belongsTo(Task::class);
    }
}
