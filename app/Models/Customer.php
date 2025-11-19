<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Customer extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'email',
        'phone',
        'company',
        'whatsapp',
        'address',
        'nic_or_dl',
        'notes',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
