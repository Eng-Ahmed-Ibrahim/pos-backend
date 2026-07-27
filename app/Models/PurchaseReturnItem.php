<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PurchaseReturnItem extends Model
{
    protected $fillable = ['purchase_return_id', 'purchase_item_id', 'quantity', 'price', 'total'];

    public function purchaseItem()
    {
        return $this->belongsTo(PurchaseItems::class);
    }
}
