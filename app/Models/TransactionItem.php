<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TransactionItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'transaction_id',
        'product_id',
        'price_category_id',
        'quantity',
        'unit_price',
        'purchase_price',
        'discount_amount',
        'tax_amount',
        'subtotal',
    ];

    public $timestamps = false;

    protected $casts = [
        'quantity' => 'integer',
        'unit_price' => 'decimal:2',
        'purchase_price' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'subtotal' => 'decimal:2',
    ];

    public function transaction()
    {
        return $this->belongsTo(Transaction::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    // Calculate profit
    public function getProfitAttribute()
    {
        return ($this->unit_price - $this->purchase_price) * $this->quantity - $this->discount_amount;
    }
}
