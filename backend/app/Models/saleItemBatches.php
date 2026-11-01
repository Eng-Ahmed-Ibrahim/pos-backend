<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class saleItemBatches extends Model
{
    protected $guarded = [];
    public function purchaseItem()
    {
        return $this->belongsTo(PurchaseItems::class, 'purchase_item_id');
    }
}
