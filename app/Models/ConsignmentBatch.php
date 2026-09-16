<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ConsignmentBatch extends Model
{
    use HasFactory;

    protected $fillable = [
        'batch_number',
        'consignor_id',
        'user_id',
        'dropoff_date',
        'settlement_date',
        'total_qty_dropped',
        'total_qty_sold',
        'total_qty_returned',
        'total_payable',
        'total_canteen_profit',
        'cashbox_id',
        'status',
        'notes',
    ];

    protected $casts = [
        'dropoff_date' => 'date',
        'settlement_date' => 'datetime',
        'total_payable' => 'float',
        'total_canteen_profit' => 'float',
    ];

    public function consignor()
    {
        return $this->belongsTo(Consignor::class);
    }

    public function cashier()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function cashbox()
    {
        return $this->belongsTo(Cashbox::class);
    }

    public function items()
    {
        return $this->hasMany(ConsignmentItem::class);
    }
}
