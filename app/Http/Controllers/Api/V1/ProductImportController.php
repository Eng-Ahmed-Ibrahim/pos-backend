<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Imports\ProductsImport;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class ProductImportController extends Controller
{
    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv|max:10240',
        ]);

        set_time_limit(0);

        $import = new ProductsImport();

        Excel::import($import, $request->file('file'));

        return response()->json([
            'status' => true,
            'data' => [
                'created_products' => $import->createdCount,
                'updated_products' => $import->updatedCount,
            ],
        ]);
    }
}