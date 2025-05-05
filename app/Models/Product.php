<?php

namespace App\Models;

use App\Traits\HasShopRestriction;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    use HasFactory;
    use HasShopRestriction;

    protected $fillable = [
        'name',
        'sku',
        'category_id',
        'purchase_price',
        'selling_price',
        'barcode',
        'product_type',
        'description',
        'images',
        'is_active',
    ];

    protected $casts = [
        'purchase_price' => 'decimal:2',
        'selling_price' => 'decimal:2',
        'images' => 'array',
        'is_active' => 'boolean',
    ];

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function stocks()
    {
        return $this->hasMany(ProductStock::class);
    }

    public function purchaseOrderItems()
    {
        return $this->hasMany(PurchaseOrderItem::class);
    }

    public function transactionItems()
    {
        return $this->hasMany(TransactionItem::class);
    }

    public function stockMovements()
    {
        return $this->hasMany(StockMovement::class);
    }

    public function stockOpnameItems()
    {
        return $this->hasMany(StockOpnameItem::class);
    }

    // Get stock for a specific shop
    public function getStockForShop($shopId)
    {
        return $this->stocks()->where('shop_id', $shopId)->first()->stock ?? 0;
    }

    // Check if product is low on stock for a specific shop
    public function isLowStock($shopId)
    {
        $stock = $this->stocks()->where('shop_id', $shopId)->first();
        if (!$stock) return false;

        return $stock->stock <= $stock->min_stock;
    }

    /**
     * Get the wholesale prices for the product.
     */
    public function wholesalePrices(): HasMany
    {
        return $this->hasMany(ProductWholesalePrice::class);
    }

    /**
     * Get active wholesale prices for a specific shop.
     *
     * @param int|null $shopId
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getActiveWholesalePrices(?int $shopId = null)
    {
        $query = $this->wholesalePrices()->where('is_active', true);

        if ($shopId) {
            $query->where(function ($q) use ($shopId) {
                $q->where('shop_id', $shopId)
                    ->orWhereNull('shop_id');
            });
        }

        return $query->orderBy('min_quantity')->get();
    }

    /**
     * Get the price for a specific quantity and shop.
     *
     * @param int $quantity
     * @param int|null $shopId
     * @return float
     */
    public function getPriceForQuantity(int $quantity, ?int $shopId = null): float
    {
        // Get shop-specific price first, then fallback to global price
        $wholesalePrice = $this->wholesalePrices()
            ->where('is_active', true)
            ->where('min_quantity', '<=', $quantity)
            ->where(function ($query) use ($shopId) {
                if ($shopId) {
                    $query->where('shop_id', $shopId)
                        ->orWhereNull('shop_id');
                }
            })
            ->orderBy('min_quantity', 'desc')
            ->first();

        // If wholesale price exists, return it; otherwise, return regular selling price
        return $wholesalePrice ? $wholesalePrice->price : $this->selling_price;
    }
}
