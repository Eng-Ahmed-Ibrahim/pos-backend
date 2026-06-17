<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SaleItems extends Model
{
    protected $fillable = ['sale_id', 'product_id', 'quantity', 'price', 'total'];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function sale()
    {
        return $this->belongsTo(Sale::class);
    }
}