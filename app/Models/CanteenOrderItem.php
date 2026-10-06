<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CanteenOrderItem extends Model
{
    protected $fillable = [
        'canteen_order_id',
        'product_id',
        'product_unit_id',
        'product_name',
        'unit_name',
        'qty',
        'unit_price',
        'subtotal',
        'notes',
    ];

    protected $casts = [
        'qty'        => 'float',
        'unit_price' => 'float',
        'subtotal'   => 'float',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(CanteenOrder::class, 'canteen_order_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(ProductUnit::class, 'product_unit_id');
    }
}
