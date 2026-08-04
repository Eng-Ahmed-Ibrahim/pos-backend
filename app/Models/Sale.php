<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Sale extends Model
{
    protected $fillable = ['customer_name', 'total', 'amount_paid','user_id','payment_method'];

    public function items()
    {
        return $this->hasMany(SaleItems::class);
    }

    public function user(){
        return $this->belongsTo(User::class,'user_id');
    }

    public function returns()
    {
        return $this->hasMany(SaleReturn::class);
    }
}
