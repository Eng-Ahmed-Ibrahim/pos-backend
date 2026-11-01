<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Supplier extends Model
{
    use SoftDeletes,HasFactory;
    protected $guarded = [];
            public function purchases(){
        return $this->hasMany(Purchase::class);
    }
}
