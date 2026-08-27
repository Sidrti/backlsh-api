<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Download extends Model
{
    use HasFactory;

    protected $table = 'downloads';

    protected $fillable = ['os', 'created_date_time', 'updated_datetime'];

    const CREATED_AT = 'created_date_time';
    const UPDATED_AT = 'updated_datetime';
}
