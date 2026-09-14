<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductReturn extends Model
{
    protected $table = 'returns';

    protected $fillable = [
        'return_number',
        'return_type',
        'reference_number',
        'customer_id',
        'customer_name',
        'supplier_id',
        'supplier_name',
        'return_date',
        'user_id',
        'total_refund_amount',
        'resolution_type',
        'reason',
        'status',
    ];

    protected $casts = [
        'return_date' => 'date',
        'total_refund_amount' => 'decimal:2',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function items()
    {
        return $this->hasMany(ReturnItem::class, 'return_id');
    }
}
