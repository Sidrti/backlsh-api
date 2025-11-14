<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Project extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'status',
        'start_date',
        'end_date',
        'created_by',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    /**
     * Get the user who created the project.
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get all members of the project.
     */
    public function members()
    {
        return $this->belongsToMany(User::class, 'project_members');
    }

    /**
     * Get all tasks for the project.
     */
    public function tasks()
    {
        return $this->hasMany(Task::class);
    }

    /**
     * Get all user activities related to this project.
     */
    public function userActivities()
    {
        return $this->hasMany(UserActivity::class);
    }

    /**
     * Get all user sub activities related to this project.
     */
    public function userSubActivities()
    {
        return $this->hasMany(UserSubActivity::class);
    }

    /**
     * Get the deadline status of the project.
     */
    public function getDeadlineStatusAttribute()
    {
        if (!$this->end_date) {
            return 'DEADLINE_NOT_ASSIGNED';
        }

        $now = now()->startOfDay();
        $endDate = $this->end_date->startOfDay();
        $daysUntilDeadline = $now->diffInDays($endDate, false);

        if ($daysUntilDeadline < 0) {
            return 'CROSSED';
        } elseif ($daysUntilDeadline <= 7) {
            return 'APPROACHING';
        }

        return 'ON_TRACK';
    }
}
