<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Sale extends Model
{
    protected $fillable = ['customer_name', 'total', 'amount_paid'];

    public function items()
    {
        return $this->hasMany(SaleItems::class);
    }


    public function returns()
    {
        return $this->hasMany(SaleReturn::class);
    }
}
