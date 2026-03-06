<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectProcessRule extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'project_id',
        'process',
        'match_type',
        'priority',
        'is_active',
    ];

    /**
     * Get the project that owns the rule.
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}