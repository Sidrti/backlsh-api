<?php

namespace App\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class AttendanceSchedule extends Model 
{
  protected $fillable = [
    'start_date', 
    'end_date',
    'start_time',
    'end_time',  
    'min_hours'
  ];

  public function users()
  {
    return $this->belongsToMany(User::class);
  }
}

?>