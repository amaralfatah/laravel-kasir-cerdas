<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'shop_id',
        'is_active',
        'last_login',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'last_login' => 'datetime',
        'is_active' => 'boolean',
    ];

    public function shop()
    {
        // Super admin tidak terikat ke shop manapun
        if ($this->isSuperAdmin()) {
            return null;
        }

        return $this->belongsTo(Branch::class);
    }

    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }

    public function purchases()
    {
        return $this->hasMany(PurchaseOrder::class, 'created_by');
    }

    public function receivedPurchases()
    {
        return $this->hasMany(PurchaseOrder::class, 'received_by');
    }

    public function expenses()
    {
        return $this->hasMany(Expense::class, 'created_by');
    }

    public function stockMovements()
    {
        return $this->hasMany(StockMovement::class);
    }

    public function conductedStockOpnames()
    {
        return $this->hasMany(StockOpname::class, 'conducted_by');
    }

    public function approvedStockOpnames()
    {
        return $this->hasMany(StockOpname::class, 'approved_by');
    }

    public function activityLogs()
    {
        return $this->hasMany(ActivityLog::class);
    }

    public function systemSettings()
    {
        return $this->hasMany(SystemSetting::class, 'updated_by');
    }

    public function isSuperAdmin()
    {
        return $this->role === 'super_admin';
    }

    public function isAdmin()
    {
        return in_array($this->role, ['owner', 'admin']);
    }

    public function isManager()
    {
        return in_array($this->role, ['owner', 'admin', 'manager']);
    }

    public function isCashier()
    {
        return $this->role === 'cashier';
    }
}
