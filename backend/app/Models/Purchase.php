<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Purchase extends Model
{
    use SoftDeletes,HasFactory;

    protected $guarded = [];

    public function items()
    {
        return $this->hasMany(PurchaseItems::class);
    }
    public function supplier()
    {
        return $this->belongsTo(Supplier::class,'supplier_id');
    }
}
