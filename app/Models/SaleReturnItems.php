<?php

// app/Models/SaleReturnItem.php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SaleReturnItems extends Model
{
    use HasFactory;

    protected $fillable = ['sale_return_id', 'sale_item_id', 'product_id', 'quantity', 'price', 'total'];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function saleItem()
    {
        return $this->belongsTo(SaleItems::class);
    }
}
