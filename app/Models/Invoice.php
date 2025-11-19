<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Invoice extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'customer_id',
        'booking_id',
        'number',
        'amount',
        'status',
        'issued_at',
        'due_at',
        'discount',
        'advance_payment',
        'due_amount',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'issued_at' => 'datetime',
        'due_at' => 'datetime',
        'discount' => 'decimal:2',
        'advance_payment' => 'decimal:2',
        'due_amount' => 'decimal:2',
    ];

    public function user() { return $this->belongsTo(User::class); }
    public function customer() { return $this->belongsTo(Customer::class); }
    public function booking() { return $this->belongsTo(Booking::class); }
    public function items() { return $this->hasMany(InvoiceItem::class); }
    public function payments() { return $this->hasMany(Payment::class, 'booking_id', 'booking_id'); }
}
