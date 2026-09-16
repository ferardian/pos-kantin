<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Employee extends Model
{
    protected $fillable = ['nik', 'name', 'department', 'phone', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    public function receivables(): HasMany
    {
        return $this->hasMany(EmployeeReceivable::class);
    }

    public function activeReceivables(): HasMany
    {
        return $this->hasMany(EmployeeReceivable::class)->whereIn('status', ['unpaid', 'partial']);
    }

    public function getTotalRemainingAttribute(): float
    {
        return (float) $this->activeReceivables()->sum('remaining');
    }
}
