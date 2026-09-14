<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductLocation extends Model
{
    protected $fillable = [
        'product_id',
        'location_id',
        'stock_physical',
        'min_stock',
    ];

    protected $casts = [
        'stock_physical' => 'decimal:2',
        'min_stock' => 'decimal:2',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function location()
    {
        return $this->belongsTo(Location::class);
    }
}
