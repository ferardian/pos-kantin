<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StockOpname extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'opname_date' => 'date',
        'total_qty_system' => 'float',
        'total_qty_physical' => 'float',
        'total_qty_diff' => 'float',
        'total_cost_diff' => 'float',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function items()
    {
        return $this->hasMany(StockOpnameItem::class);
    }
}
