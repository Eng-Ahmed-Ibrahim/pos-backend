<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InventorySnapshot extends Model
{
    protected $fillable = [
        'snapshot_date', 'product_id', 'unit_id',
        'remaining_stock', 'avg_cost_price', 'total_value', 'type',
    ];

    protected $casts = [
        'snapshot_date' => 'date',
        'remaining_stock' => 'decimal:3',
        'avg_cost_price' => 'decimal:3',
        'total_value' => 'decimal:3',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}