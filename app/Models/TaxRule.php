<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TaxRule extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'rate',
        'applies_to',
        'reference_id',
        'is_active',
    ];

    protected $casts = [
        'rate' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    // Get referenced entity (category or product)
    public function getReference()
    {
        if ($this->applies_to === 'all') return null;

        if ($this->applies_to === 'category') {
            return Category::find($this->reference_id);
        }

        if ($this->applies_to === 'product') {
            return Product::find($this->reference_id);
        }

        return null;
    }
}
