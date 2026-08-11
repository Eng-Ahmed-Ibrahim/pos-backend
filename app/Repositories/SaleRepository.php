<?php

namespace App\Repositories;

use App\Models\Sale;

class SaleRepository{


    public function create(array  $data){
        return Sale::create($data);
    }
}