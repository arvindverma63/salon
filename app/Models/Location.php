<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Location extends Model
{
    use HasFactory;
    protected $table = "locations";
    protected $fillable = [
        'name',
        'address',
        'city',
        'phone_number',
        'post_code',
        'location_id',
        'isActive',
    ];
}
