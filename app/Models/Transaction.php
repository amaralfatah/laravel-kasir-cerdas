<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Transaction extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'invoice_number',
        'transaction_type',
        'user_id',
        'shop_id',
        'customer_id',
        'subtotal',
        'discount_amount',
        'tax_amount',
        'service_fee',
        'total_amount',
        'payment_method_id',
        'payment_status',
        'notes',
        'transaction_date',
    ];

    protected $casts = [
        'subtotal' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'service_fee' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'transaction_date' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function shop()
    {
        return $this->belongsTo(Shop::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function paymentMethod()
    {
        return $this->belongsTo(PaymentMethod::class);
    }

    public function items()
    {
        return $this->hasMany(TransactionItem::class);
    }

    public function payments()
    {
        return $this->hasMany(TransactionPayment::class);
    }

    // Generate Invoice Number
    public static function generateInvoiceNumber($shopId)
    {
        $shop = Shop::find($shopId);
        $shopCode = substr(strtoupper($shop->name), 0, 3);
        $date = now()->format('Ymd');

        $latestInvoice = self::where('invoice_number', 'like', $shopCode . '-' . $date . '%')
            ->latest()
            ->first();

        $sequence = $latestInvoice ? intval(substr($latestInvoice->invoice_number, -4)) + 1 : 1;

        return $shopCode . '-' . $date . '-' . str_pad($sequence, 4, '0', STR_PAD_LEFT);
    }

    // Calculate total paid
    public function getTotalPaidAttribute()
    {
        return $this->payments()->sum('amount');
    }

    // Calculate remaining balance
    public function getRemainingBalanceAttribute()
    {
        return $this->total_amount - $this->total_paid;
    }

    // Calculate profit
    public function getProfitAttribute()
    {
        $profit = 0;
        foreach ($this->items as $item) {
            $profit += ($item->unit_price - $item->purchase_price) * $item->quantity;
        }
        return $profit - $this->discount_amount;
    }
}
