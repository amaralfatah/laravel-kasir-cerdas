<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ProductPriceRule extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'shop_id',
        'price_category_id',
        'min_quantity',
        'price',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'min_quantity' => 'integer',
    ];

    /**
     * Get the product that owns this price rule
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Get the shop that owns this price rule
     */
    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    /**
     * Get the price category that owns this price rule
     */
    public function priceCategory(): BelongsTo
    {
        return $this->belongsTo(PriceCategory::class);
    }

    /**
     * Scope untuk toko tertentu atau semua toko
     */
    public function scopeForShop($query, $shopId)
    {
        return $query->where(function ($q) use ($shopId) {
            $q->where('shop_id', $shopId)
                ->orWhereNull('shop_id');
        });
    }

    /**
     * Mendapatkan harga yang berlaku berdasarkan produk, kategori harga, toko dan jumlah
     */
    public static function getApplicablePrice($productId, $priceCategoryId, $shopId, $quantity)
    {
        return self::where('product_id', $productId)
            ->where('price_category_id', $priceCategoryId)
            ->where('min_quantity', '<=', $quantity)
            ->forShop($shopId)
            ->active()
            ->current()
            ->orderBy('min_quantity', 'desc') // Periksa aturan untuk jumlah terbesar terlebih dahulu
            ->first();
    }
}