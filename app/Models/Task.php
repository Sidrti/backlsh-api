<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Task extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id',
        'name',
        'description',
        'status',
        'priority',
        'due_date',
        'completed_at',
        'assignee_id',
    ];

    protected $casts = [
        'due_date' => 'date',
        'completed_at' => 'date',
    ];

    /**
     * Get the project that owns the task.
     */
    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * Get the user assigned to the task.
     */
    public function assignee()
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }

    /**
     * Get all user activities related to this task.
     */
    public function userActivities()
    {
        return $this->hasMany(UserActivity::class);
    }

    /**
     * Get all user sub activities related to this task.
     */
    public function userSubActivities()
    {
        return $this->hasMany(UserSubActivity::class);
    }

    /**
     * Get all of the checklists for the task.
     */
    public function checklists()
    {
        return $this->hasMany(Checklist::class);
    }

    /**
     * Get the deadline status of the task.
     */
    public function getDeadlineStatusAttribute()
    {
        if ($this->status === 'DONE') {
            if ($this->due_date && $this->completed_at && $this->completed_at->gt($this->due_date)) {
                return 'COMPLETED_LATE';
            }

            return 'COMPLETED';
        }

        if (!$this->due_date) {
            return 'DEADLINE_NOT_ASSIGNED';
        }

        $now = now()->startOfDay();
        $dueDate = $this->due_date->startOfDay();
        $daysUntilDeadline = $now->diffInDays($dueDate, false);

        if ($daysUntilDeadline < 0) {
            return 'CROSSED';
        } elseif ($daysUntilDeadline <= 3) {
            return 'APPROACHING';
        }

        return 'ON_TRACK';
    }
}
