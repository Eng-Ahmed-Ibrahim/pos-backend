<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use SoftDeletes,HasFactory;
    protected $fillable = [
        'barcode',
        'name',
        'price',
        'category_id',
        'sub_category_id',
        'brand_id',
        'minimum_stock',
        'created_at',
        'updated_at',
        'deleted_at',
        'unit_id'

    ];

    public function category()
    {
        return $this->belongsTo(Category::class);
    }
    public function unit()
    {
        return $this->belongsTo(Unit::class,'unit_id');
    }
    public function sub_category()
    {
        return $this->belongsTo(SubCategory::class);
    }
    public function purchaseItems()
    {
        return $this->hasMany(PurchaseItems::class);
    }
}
