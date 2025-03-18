<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductTransaction extends Model
{
    protected $table = 'product_transaction';

    protected $fillable = ['user_id',
    'location_id',
    'product_id',
    'quantity'];

    use HasFactory;
}
