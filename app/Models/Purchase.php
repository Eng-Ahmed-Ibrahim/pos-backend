<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Purchase extends Model
{
    use SoftDeletes;

    protected $guarded = [];

    public function items()
    {
        return $this->hasMany(PurchaseItems::class);
    }
    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }
}
