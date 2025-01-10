<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Process extends Model
{
    use HasFactory;

    protected $fillable = ['process_name','icon','type'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
    // public function getIconAttribute($value)
    // {
    //     if (filter_var($value, FILTER_VALIDATE_URL)) {
    //         return $value; // It's already a full URL, so return it as is
    //     }
    //     return $value != null ? config('app.asset_url').$value : $value;
    // }
    public function getIconAttribute($value)
    {
        return $value ?config('app.asset_url'). Storage::url($value) : null;
    }
}
