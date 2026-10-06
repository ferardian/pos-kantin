<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CanteenOrder extends Model
{
    protected $fillable = [
        'order_number',
        'employee_id',
        'order_type',
        'delivery_location',
        'recipient_name',
        'recipient_phone',
        'payment_method',
        'payment_status',
        'status',
        'total_items',
        'total_amount',
        'notes',
        'cancel_reason',
        'confirmed_at',
        'completed_at',
        'cancelled_at',
        'transaction_id',
    ];

    protected $casts = [
        'total_amount' => 'float',
        'total_items'  => 'integer',
        'confirmed_at' => 'datetime',
        'completed_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    protected $appends = ['status_label', 'order_type_label'];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(CanteenOrderItem::class);
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'pending'          => 'Menunggu Konfirmasi',
            'confirmed'        => 'Dikonfirmasi',
            'preparing'        => 'Sedang Disiapkan',
            'on_delivery'      => 'Sedang Diantar ke Mess',
            'ready_for_pickup' => 'Siap Diambil di Kantin',
            'completed'        => 'Selesai',
            'cancelled'        => 'Dibatalkan',
            default            => ucfirst($this->status),
        };
    }

    public function getOrderTypeLabelAttribute(): string
    {
        return match ($this->order_type) {
            'delivery' => 'Antar ke Mess/Ruangan',
            'pickup'   => 'Ambil Sendiri di Kantin',
            default    => ucfirst($this->order_type),
        };
    }
}
