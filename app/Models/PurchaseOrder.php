<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PurchaseOrder extends Model
{
    use HasFactory;

    protected $fillable = [
        'po_number',
        'supplier_id',
        'branch_id',
        'total',
        'status',
        'created_by',
        'received_by',
        'notes',
        'order_date',
    ];

    protected $casts = [
        'total' => 'decimal:2',
        'order_date' => 'date',
    ];

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function shop()
    {
        return $this->belongsTo(Shop::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function receivedBy()
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    public function items()
    {
        return $this->hasMany(PurchaseOrderItem::class, 'po_id');
    }

    // Generate PO Number
    public static function generatePoNumber()
    {
        $latestPo = self::latest()->first();
        $number = $latestPo ? intval(substr($latestPo->po_number, 3)) + 1 : 1;

        return 'PO-' . str_pad($number, 8, '0', STR_PAD_LEFT);
    }
}
