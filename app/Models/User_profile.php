<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class User_profile extends Model
{
    use HasFactory;
    protected $table = 'user_profiles';
    protected $fillable = [
        'user_id',
        'email',
        'phone_no',
        'firstName',
        'lastName',
        'gender',
        'gdpr_sms_active',
        'gdpr_email_active',
        'referred_by',
        'preferred_location',
        'avatar',
        'active',
        'available_balance',
        'total_spend',
        'address',
        'post_code',
        'phone_number',
        'dob',
    ];
    public function user()
    {
        return $this->belongsTo(User::class);
    }

}


