<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Exports\ProductsKGExport;
use Maatwebsite\Excel\Facades\Excel;

class ScaleController extends Controller
{
    public function download()
    {
        return Excel::download(new ProductsKGExport, 'scale_products.xlsx');
    }
}
