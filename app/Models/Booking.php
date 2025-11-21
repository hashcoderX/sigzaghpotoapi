<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Booking extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'customer_id',
        'event_date',
        'location',
        'status',
        'notes',
        'wedding_shoot_date',
        'preshoot_date',
        'homecoming_date',
        'function_date',
        'event_covering_date',
        'custom_plan_date',
        'wedding_shoot_location',
        'preshoot_location',
        'homecoming_location',
        'function_location',
        'event_covering_location',
        'custom_plan_location',
    ];

    protected $casts = [
        'event_date' => 'datetime',
        'wedding_shoot_date' => 'datetime',
        'preshoot_date' => 'datetime',
        'homecoming_date' => 'datetime',
        'function_date' => 'datetime',
        'event_covering_date' => 'datetime',
        'custom_plan_date' => 'datetime',
    ];

    public function user() { return $this->belongsTo(User::class); }
    public function customer() { return $this->belongsTo(Customer::class); }
}
