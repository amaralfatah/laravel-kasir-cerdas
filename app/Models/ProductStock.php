<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductStock extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'branch_id',
        'stock',
        'min_stock',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function shop()
    {
        return $this->belongsTo(Shop::class);
    }

    // Adjust stock
    public function adjustStock($quantity, $movementType, $referenceType, $referenceId, $userId, $notes = null)
    {
        // Update stock
        $this->stock += $quantity;
        $this->save();

        // Create stock movement record
        StockMovement::create([
            'product_id' => $this->product_id,
            'branch_id' => $this->branch_id,
            'quantity' => $quantity,
            'movement_type' => $movementType,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'notes' => $notes,
            'user_id' => $userId,
        ]);

        return $this;
    }
}
