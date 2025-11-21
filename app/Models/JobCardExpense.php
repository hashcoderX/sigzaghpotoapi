<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class JobCardExpense extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'job_card_id',
        'event_type',
        'amount',
        'description',
        'expense_date',
        'vendor',
        'receipt_path',
        'metadata',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'expense_date' => 'datetime',
        'metadata' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function jobCard()
    {
        return $this->belongsTo(JobCard::class);
    }
}
