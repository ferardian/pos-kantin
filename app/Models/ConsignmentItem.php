<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ConsignmentItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'consignment_batch_id',
        'product_id',
        'product_unit_id',
        'qty_dropped',
        'qty_sold',
        'qty_returned',
        'cost_price',
        'selling_price',
        'subtotal_payable',
        'subtotal_profit',
    ];

    protected $casts = [
        'cost_price' => 'float',
        'selling_price' => 'float',
        'subtotal_payable' => 'float',
        'subtotal_profit' => 'float',
    ];

    public function batch()
    {
        return $this->belongsTo(ConsignmentBatch::class, 'consignment_batch_id');
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
