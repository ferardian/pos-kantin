<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $fillable = [
        'sku',
        'barcode',
        'name',
        'category_id',
        'brand_id',
        'min_stock',
        'stock_physical',
        'stock_booked',
        'specifications',
        'image_url',
        'description',
    ];

    protected $casts = [
        'specifications' => 'array',
        'stock_physical' => 'decimal:2',
        'stock_booked' => 'decimal:2',
    ];

    protected $appends = ['stock_available'];

    public function getStockAvailableAttribute()
    {
        return max(0, (float)$this->stock_physical - (float)$this->stock_booked);
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function brand()
    {
        return $this->belongsTo(Brand::class);
    }

    public function units()
    {
        return $this->hasMany(ProductUnit::class);
    }

    public function baseUnit()
    {
        return $this->hasOne(ProductUnit::class)->where('is_base_unit', true);
    }

    public function transactionItems()
    {
        return $this->hasMany(TransactionItem::class);
    }

    public function salesOrderItems()
    {
        return $this->hasMany(SalesOrderItem::class);
    }

    public function stockAdjustments()
    {
        return $this->hasMany(StockAdjustment::class);
    }

    public function productLocations()
    {
        return $this->hasMany(ProductLocation::class);
    }
}
