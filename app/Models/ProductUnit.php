<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductUnit extends Model
{
    protected $fillable = [
        'product_id', 'unit_name', 'conversion_ratio',
        'cost_price', 'price_retail', 'is_base_unit',
    ];

    protected $casts = [
        'conversion_ratio' => 'float',
        'cost_price'       => 'float',
        'price_retail'     => 'float',
        'is_base_unit'     => 'boolean',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
