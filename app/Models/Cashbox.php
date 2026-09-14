<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Cashbox extends Model
{
    protected $fillable = [
        'name',
        'type',
        'account_number',
        'balance',
        'is_default',
    ];

    protected $casts = [
        'balance' => 'decimal:2',
        'is_default' => 'boolean',
    ];

    public function transactions()
    {
        return $this->hasMany(CashTransaction::class);
    }
}
