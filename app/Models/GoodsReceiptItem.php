<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GoodsReceiptItem extends Model
{
    protected $fillable = [
        'goods_receipt_id',
        'product_id',
        'product_unit_id',
        'qty_received',
        'conversion_ratio',
        'base_qty_added',
        'cost_price_per_unit',
        'subtotal_cost',
    ];

    protected $casts = [
        'qty_received' => 'decimal:2',
        'conversion_ratio' => 'decimal:2',
        'base_qty_added' => 'decimal:2',
        'cost_price_per_unit' => 'decimal:2',
        'subtotal_cost' => 'decimal:2',
    ];

    public function goodsReceipt()
    {
        return $this->belongsTo(GoodsReceipt::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function unit()
    {
        return $this->belongsTo(ProductUnit::class, 'product_unit_id');
    }
}
