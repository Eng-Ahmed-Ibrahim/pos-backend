<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Wastes extends Model
{
    protected $guarded = [];
    public function itemBatches()
    {
        return $this->hasMany(WasteItemBatches::class, 'waste_id');
    }
    public function product()
    {
        return $this->belongsTo(Product::class);
    }
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
