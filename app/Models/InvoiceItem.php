<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InvoiceItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'invoice_id',
        'service',
        'qty',
        'amount',
        'sub_amount',
    ];

    protected $casts = [
        'qty' => 'integer',
        'amount' => 'decimal:2',
        'sub_amount' => 'decimal:2',
    ];

    public function invoice() { return $this->belongsTo(Invoice::class); }
}
