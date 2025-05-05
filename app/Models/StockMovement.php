<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StockMovement extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'branch_id',
        'quantity',
        'movement_type',
        'reference_type',
        'reference_id',
        'notes',
        'user_id',
    ];

    const UPDATED_AT = null;

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function shop()
    {
        return $this->belongsTo(Shop::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // Get reference model
    public function reference()
    {
        if (!$this->reference_type || !$this->reference_id) return null;

        $model = 'App\\Models\\' . str_replace(' ', '', ucwords(str_replace('_', ' ', $this->reference_type)));

        return $model::find($this->reference_id);
    }
}
