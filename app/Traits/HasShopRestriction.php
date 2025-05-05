<?php

namespace App\Traits;

use App\Scopes\ShopScope;

trait HasShopRestriction
{
    protected static function bootHasShopRestriction()
    {
        static::addGlobalScope(new ShopScope);
    }

    // Jika perlu, disable shop scope untuk query tertentu
    public function scopeIgnoreShopRestriction($query)
    {
        return $query->withoutGlobalScope(ShopScope::class);
    }
}
