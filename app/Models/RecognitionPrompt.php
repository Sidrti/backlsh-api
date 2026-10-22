<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RecognitionPrompt extends Model
{
    use HasFactory;

    protected $fillable = [
        'admin_id',
        'candidate_user_id',
        'trigger_key',
        'title',
        'message',
        'suggested_preset',
        'status', // PENDING, CONVERTED, DISMISSED
        'sent_at',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
    ];

    /**
     * The admin / manager receiving this prompt
     */
    public function admin()
    {
        return $this->belongsTo(User::class, 'admin_id');
    }

    /**
     * The team member who triggered the signal
     */
    public function candidate()
    {
        return $this->belongsTo(User::class, 'candidate_user_id');
    }

    /**
     * Scope for pending active prompts
     */
    public function scopePending($query)
    {
        return $query->where('status', 'PENDING');
    }

    /**
     * Scope for recent prompts
     */
    public function scopeRecent($query, int $days = 7)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }
}
