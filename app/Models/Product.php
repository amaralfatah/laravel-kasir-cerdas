<?php

namespace App\Models;

use App\Traits\HasShopRestriction;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use HasFactory;
    use HasShopRestriction;
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
     * Mendapatkan semua harga yang tersedia untuk produk ini di toko tertentu
     */
    public function getAvailablePrices($shopId = null)
    {
        $query = $this->priceRules()
            ->with('priceCategory')
            ->active()
            ->current();

        if ($shopId) {
            $query->forShop($shopId);
        }

        return $query->get()->groupBy('price_category_id');
    }

    /**
     * Memeriksa apakah produk memiliki harga khusus
     */
    public function hasMultiplePriceCategories($shopId = null)
    {
        $categoriesCount = $this->priceRules()
            ->active()
            ->current()
            ->when($shopId, function ($query, $shopId) {
                return $query->forShop($shopId);
            })
            ->distinct('price_category_id')
            ->count('price_category_id');

        return $categoriesCount > 1;
    }

    /**
     * Mendapatkan harga berdasarkan kategori harga, jumlah, dan toko
     */
    public function getPriceFor($priceCategoryId, $quantity = 1, $shopId = null)
    {
        $rule = ProductPriceRule::getApplicablePrice(
            $this->id,
            $priceCategoryId,
            $shopId,
            $quantity
        );

        return $rule ? $rule->price : $this->selling_price;
    }
}
