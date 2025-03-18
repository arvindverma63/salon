<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ServiceTransaction extends Model
{
    protected $table = 'service_transactions';
    protected $fillable = ['user_id',
    'quantity',
    'type',
    'location',
    'service_id'];
    use HasFactory;
}
