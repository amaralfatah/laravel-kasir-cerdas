<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use HasFactory;
    // Remove HasShopRestriction trait as products don't have a direct shop_id
    use SoftDeletes;

    protected $fillable = [
        'name',
        'sku',
        'category_id',
        'purchase_price',
        'selling_price',
        'barcode',
        'description',
        'images',
        'is_using_stock',
        'is_active',
        'weight',
        'unit',
        'discount_percentage',
        'rack_placement',
        'information',
    ];

    protected $casts = [
        'purchase_price' => 'decimal:2',
        'selling_price' => 'decimal:2',
        'images' => 'array',
        'is_using_stock' => 'boolean',
        'is_active' => 'boolean',
        'deleted_at' => 'datetime',
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
        // Only check stock if this product uses stock
        if (!$this->is_using_stock) {
            return null;
        }

        return $this->stocks()->where('shop_id', $shopId)->first()->stock ?? 0;
    }

    // Check if product is low on stock for a specific shop
    public function isLowStock($shopId)
    {
        // Only check for low stock if this product uses stock
        if (!$this->is_using_stock) {
            return false;
        }

        $stock = $this->stocks()->where('shop_id', $shopId)->first();
        if (!$stock)
            return false;

        return $stock->stock <= $stock->min_stock;
    }

    /**
     * Get all price rules for this product
     */
    public function priceRules(): HasMany
    {
        return $this->hasMany(ProductPriceRule::class);
    }

    /**
     * Get all available prices for this product in a specific shop
     */
    public function getAvailablePrices($shopId = null)
    {
        $query = $this->priceRules()
            ->with('priceCategory');

        // Apply active and current filters if these scopes exist
        if (method_exists(ProductPriceRule::class, 'scopeActive')) {
            $query->active();
        }

        if (method_exists(ProductPriceRule::class, 'scopeCurrent')) {
            $query->current();
        }

        if ($shopId) {
            $query->where(function($q) use ($shopId) {
                $q->where('shop_id', $shopId)
                    ->orWhereNull('shop_id');
            });
        }

        return $query->get()->groupBy('price_category_id');
    }

    /**
     * Check if product has multiple price categories
     */
    public function hasMultiplePriceCategories($shopId = null)
    {
        $query = $this->priceRules();

        // Apply active and current filters if these scopes exist
        if (method_exists(ProductPriceRule::class, 'scopeActive')) {
            $query->active();
        }

        if (method_exists(ProductPriceRule::class, 'scopeCurrent')) {
            $query->current();
        }

        if ($shopId) {
            $query->where(function($q) use ($shopId) {
                $q->where('shop_id', $shopId)
                    ->orWhereNull('shop_id');
            });
        }

        $categoriesCount = $query->distinct('price_category_id')
            ->count('price_category_id');

        return $categoriesCount > 1;
    }

    /**
     * Get price for a specific price category, quantity, and shop
     */
    public function getPriceFor($priceCategoryId, $quantity = 1, $shopId = null)
    {
        // Check if static method exists before calling it
        if (method_exists(ProductPriceRule::class, 'getApplicablePrice')) {
            $rule = ProductPriceRule::getApplicablePrice(
                $this->id,
                $priceCategoryId,
                $shopId,
                $quantity
            );

            return $rule ? $rule->price : $this->selling_price;
        }

        // Fallback if the static method doesn't exist
        $query = $this->priceRules()
            ->where('price_category_id', $priceCategoryId)
            ->where('min_quantity', '<=', $quantity)
            ->orderBy('min_quantity', 'desc');

        if ($shopId) {
            $query->where(function($q) use ($shopId) {
                $q->where('shop_id', $shopId)
                    ->orWhereNull('shop_id');
            });
        } else {
            $query->whereNull('shop_id');
        }

        $rule = $query->first();

        return $rule ? $rule->price : $this->selling_price;
    }

    // Helper method to get stock in a specific shop
    public function getStockByShopId($shopId)
    {
        return $this->stocks()->where('shop_id', $shopId)->first();
    }

    // Add shop scope for filtering products by shops
    public function scopeForShops($query, array $shopIds)
    {
        return $query->whereHas('stocks', function($q) use ($shopIds) {
            $q->whereIn('shop_id', $shopIds);
        });
    }

    // Add shop scope for filtering products by a single shop
    public function scopeForShop($query, $shopId)
    {
        return $query->whereHas('stocks', function($q) use ($shopId) {
            $q->where('shop_id', $shopId);
        });
    }
}
