<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PurchaseReturn extends Model
{
    protected $fillable = ['purchase_id', 'total_amount', 'reason','supplier_id'];

    public function purchase()
    {
        return $this->belongsTo(Purchase::class);
    }
    public function items()
    {
        return $this->hasMany(PurchaseReturnItem::class);
    }
}
