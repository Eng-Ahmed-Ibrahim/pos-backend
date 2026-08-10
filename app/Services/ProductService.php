<?php

namespace App\Services;

use App\Helpers\Helpers;

class ProductService
{
    public function clear(): void
    {
        Helpers::delete_products();
    }
}