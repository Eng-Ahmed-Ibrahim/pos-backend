<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PurchaseReturn extends Model
{
    protected $fillable = ['purchase_id', 'total_amount','user_id', 'reason','supplier_id'];


    public function purchase()
    {
        return $this->belongsTo(Purchase::class);
    }
    public function items()
    {
        return $this->hasMany(PurchaseReturnItem::class);
    } 
       public function supplier()
    {
        return $this->belongsTo(Supplier::class,'supplier_id');
    }
       public function user()
    {
        return $this->belongsTo(User::class,'user_id');
    }
}
