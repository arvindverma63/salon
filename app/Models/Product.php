<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $tables = "products";
    protected $fillable = [
        'name',
        'brand',
        'description',
        'price',
        'image',
        'type',
        'stock01',
        'stock02',
        'stock03'
    ];
    use HasFactory;
}
