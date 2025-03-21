<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Service extends Model
{
    protected $table = "services";
    protected $fillable = ['serviceName',
    'minutesAvailable',
    'price'
];

    use HasFactory;
}
