<?php

namespace App\Services;

use App\Models\Sale;
use App\Models\SaleReturn;

class SaleReturnService
{

    public function add_return($data) {
        $sale= $this->get_sale($data['id']);
        $saleReturn = $this->create_return_record($sale->id,$data['user_id']);
        

    }
    public function get_sale($id)
    {
        $sale = Sale::with('items')
            ->where('id', $id)
            ->lockForUpdate()
            ->first();
        return $sale;
    }

    public function create_return_record($sale_id,$user_id)
    {
        return SaleReturn::create([
            'sale_id' => $sale_id,
            'total_amount'   => 0,
            'user_id' => $user_id
        ]);
    }
}
