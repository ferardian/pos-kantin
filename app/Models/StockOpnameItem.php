<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StockOpnameItem extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'qty_system' => 'float',
        'qty_physical' => 'float',
        'qty_difference' => 'float',
        'cost_price_per_unit' => 'float',
        'subtotal_cost_diff' => 'float',
    ];

    public function stockOpname()
    {
        return $this->belongsTo(StockOpname::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
