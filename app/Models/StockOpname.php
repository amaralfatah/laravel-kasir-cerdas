<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StockOpname extends Model
{
    use HasFactory;

    protected $table = 'stock_opname';

    protected $fillable = [
        'shop_id',
        'status',
        'notes',
        'conducted_by',
        'approved_by',
        'conducted_at',
    ];

    protected $casts = [
        'conducted_at' => 'date',
    ];

    public function shop()
    {
        return $this->belongsTo(Shop::class);
    }

    public function conductedBy()
    {
        return $this->belongsTo(User::class, 'conducted_by');
    }

    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function items()
    {
        return $this->hasMany(StockOpnameItem::class, 'stock_opname_id');
    }

    // Calculate total variance
    public function getTotalVarianceAttribute()
    {
        return $this->items()->sum('variance');
    }

    // Calculate absolute variance
    public function getAbsoluteVarianceAttribute()
    {
        return $this->items()->sum(DB::raw('ABS(variance)'));
    }
}
