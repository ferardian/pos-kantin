<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GoodsReceipt extends Model
{
    protected $fillable = [
        'receipt_number',
        'supplier_id',
        'supplier_name',
        'supplier_invoice_number',
        'receipt_date',
        'receiver_id',
        'location_id',
        'total_cost_amount',
        'notes',
    ];

    protected $casts = [
        'receipt_date' => 'date',
        'total_cost_amount' => 'decimal:2',
    ];

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function receiver()
    {
        return $this->belongsTo(User::class, 'receiver_id');
    }

    public function location()
    {
        return $this->belongsTo(Location::class);
    }

    public function items()
    {
        return $this->hasMany(GoodsReceiptItem::class);
    }
}
