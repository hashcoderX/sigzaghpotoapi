<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'customer_id',
        'booking_id',
        'invoice_id',
        'job_card_id',
        'amount',
        'currency',
        'method',
        'reference',
        'status',
        'paid_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'paid_at' => 'datetime',
    ];

    public function user() { return $this->belongsTo(User::class); }
    public function customer() { return $this->belongsTo(Customer::class); }
    public function booking() { return $this->belongsTo(Booking::class); }
    public function invoice() { return $this->belongsTo(Invoice::class); }
    public function jobCard() { return $this->belongsTo(JobCard::class); }
}
