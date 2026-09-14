<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReceivablePayment extends Model
{
    protected $fillable = [
        'employee_receivable_id', 'cashier_id', 'amount', 'payment_date', 'notes'
    ];

    protected $casts = ['amount' => 'float', 'payment_date' => 'date'];

    public function receivable(): BelongsTo
    {
        return $this->belongsTo(EmployeeReceivable::class, 'employee_receivable_id');
    }

    public function cashier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cashier_id');
    }
}
