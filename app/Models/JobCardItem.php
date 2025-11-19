<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class JobCardItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'job_card_id',
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

    public function jobCard() { return $this->belongsTo(JobCard::class); }
}
