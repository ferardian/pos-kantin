<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EmployeeReceivable extends Model
{
    protected $fillable = [
        'employee_id', 'transaction_id', 'amount', 'remaining', 'notes', 'status'
    ];

    protected $casts = ['amount' => 'float', 'remaining' => 'float'];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(ReceivablePayment::class);
    }
}
