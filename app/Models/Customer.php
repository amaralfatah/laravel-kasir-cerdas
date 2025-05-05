<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Customer extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'phone',
        'email',
        'address',
        'points',
        'is_active',
    ];

    protected $casts = [
        'points' => 'integer',
        'is_active' => 'boolean',
    ];

    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }

    // Calculate total spent
    public function getTotalSpentAttribute()
    {
        return $this->transactions()->where('payment_status', 'completed')->sum('total_amount');
    }

    // Calculate total transactions
    public function getTotalTransactionsAttribute()
    {
        return $this->transactions()->count();
    }
}
