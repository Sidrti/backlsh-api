<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Subscription extends Model
{
    use HasFactory;
     protected $fillable = [
        'stripe_id',
        'user_id',
        'status',
        'name',
        'stripe_status',
        'stripe_price',
        'quantity',
        'trial_ends_at',
        'ends_at'
     ];
}
