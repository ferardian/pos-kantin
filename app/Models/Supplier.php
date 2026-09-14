<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Supplier extends Model
{
    protected $fillable = [
        'name',
        'phone',
        'email',
        'contact_person',
        'address',
    ];

    public function goodsReceipts()
    {
        return $this->hasMany(GoodsReceipt::class);
    }
}
