<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class JobCard extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'booking_id',
        'title',
        'description',
        'status',
        'assigned_to',
        'due_date',
        'confirmed_amount',
        'advance_payment',
        'discount',
    ];

    protected $casts = [
        'due_date' => 'datetime',
        'confirmed_amount' => 'decimal:2',
        'advance_payment' => 'decimal:2',
        'discount' => 'decimal:2',
    ];

    public function user() { return $this->belongsTo(User::class); }
    public function booking() { return $this->belongsTo(Booking::class); }
    public function items() { return $this->hasMany(JobCardItem::class); }
}
