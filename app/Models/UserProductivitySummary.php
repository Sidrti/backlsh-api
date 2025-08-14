<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserProductivitySummary extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'date','productive_seconds', 'nonproductive_seconds', 'neutral_seconds', 'total_seconds'];
}
