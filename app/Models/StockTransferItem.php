<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockTransferItem extends Model
{
    protected $fillable = [
        'stock_transfer_id',
        'product_id',
        'product_unit_id',
        'qty',
        'conversion_ratio',
        'base_qty',
    ];

    protected $casts = [
        'qty' => 'decimal:2',
        'conversion_ratio' => 'decimal:2',
        'base_qty' => 'decimal:2',
    ];

    public function stockTransfer()
    {
        return $this->belongsTo(StockTransfer::class);
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
