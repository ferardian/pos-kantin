<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Transaction extends Model
{
    protected $fillable = [
        'invoice_number', 'cashier_id',
        'total_gross', 'discount', 'total_net',
        'paid_amount', 'change_amount',
        'payment_method', 'notes',
    ];

    protected $casts = [
        'total_gross'   => 'float',
        'discount'      => 'float',
        'total_net'     => 'float',
        'paid_amount'   => 'float',
        'change_amount' => 'float',
    ];

    public function cashier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cashier_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(TransactionItem::class);
    }

    public function receivable(): HasMany
    {
        return $this->hasMany(EmployeeReceivable::class);
    }
}
