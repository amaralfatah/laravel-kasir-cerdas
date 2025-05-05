<?php

namespace App\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Auth;

class ShopScope implements Scope
{
    public function apply(Builder $builder, Model $model)
    {
        $user = Auth::user();

        // Jika super_admin, tidak menerapkan pembatasan
        if (!$user || $user->role === 'super_admin') {
            return;
        }

        // Terapkan pembatasan branch_id untuk user lain
        $builder->where($model->getTable() . '.branch_id', $user->branch_id);
    }
}
