<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PaymentMethod extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'is_digital',
        'fee_percentage',
        'is_active',
    ];

    protected $casts = [
        'is_digital' => 'boolean',
        'fee_percentage' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }

    public function transactionPayments()
    {
        return $this->hasMany(TransactionPayment::class);
    }

    // Calculate fee for an amount
    public function calculateFee($amount)
    {
        return $amount * ($this->fee_percentage / 100);
    }
}
