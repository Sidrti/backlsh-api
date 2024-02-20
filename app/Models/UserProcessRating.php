<?php

namespace App\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class UserProcessRating extends Model 
{
  protected $fillable = [
    'user_id', 
    'process_id',
    'rating',
  ];
}

?>