<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PurchaseOrderItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'po_id',
        'product_id',
        'quantity',
        'received_quantity',
        'unit_price',
    ];

    public $timestamps = false;

    protected $casts = [
        'unit_price' => 'decimal:2',
    ];

    public function purchaseOrder()
    {
        return $this->belongsTo(PurchaseOrder::class, 'po_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    // Calculate subtotal
    public function getSubtotalAttribute()
    {
        return $this->quantity * $this->unit_price;
    }

    // Calculate received subtotal
    public function getReceivedSubtotalAttribute()
    {
        return $this->received_quantity * $this->unit_price;
    }
}
