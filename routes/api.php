<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\SaleController;
use App\Http\Controllers\Api\V1\UnitController;
use App\Http\Controllers\Api\V1\RolesController;
use App\Http\Controllers\Api\V1\UsersController;
use App\Http\Controllers\Api\v1\WasteController;
use App\Http\Controllers\Api\V1\ReportsController;
use App\Http\Controllers\Api\V1\CategoryController;
use App\Http\Controllers\Api\V1\ProductsController;
use App\Http\Controllers\Api\V1\PurchaseController;
use App\Http\Controllers\Api\V1\SettingsController;
use App\Http\Controllers\Api\V1\SupplierController;
use App\Http\Controllers\Api\V1\SaleReturnController;
use App\Http\Controllers\Api\V1\SubCategoryController;
use App\Http\Controllers\Api\V1\ProductImportController;
use App\Http\Controllers\Api\v1\FinancialReportController;
use App\Http\Controllers\Api\V1\WarehouseInventoryController;

Route::prefix('v1')->group(function () {

    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login'])->name('login');

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/user', [AuthController::class, 'user']);

        Route::post('/products/import', [ProductImportController::class, 'import']);
        Route::get('/wastes', [WasteController::class, 'index']);
        Route::post('/wastes', [WasteController::class, 'store']);
        Route::apiResource('categories', CategoryController::class);

        Route::apiResource('sub-categories', SubCategoryController::class);


        Route::apiResource('products', ProductsController::class);
        Route::apiResource('suppliers', SupplierController::class);
        Route::apiResource('purchases', PurchaseController::class);
        Route::post('purchases/{id}/return', [PurchaseController::class, 'storeReturn']);
        Route::apiResource('units', UnitController::class)->only(['index', 'store', 'update', 'destroy']);

        Route::apiResource('users', UsersController::class);
        Route::apiResource('roles', RolesController::class);
        Route::get('/purchase/create-page', [PurchaseController::class, 'create']);
        Route::post('sales', [SaleController::class, 'store']);
        Route::get('sales/{id}', [SaleController::class, 'show']);

        Route::get('purchase/return', [SaleReturnController::class, 'index']);
        Route::get('purchase/return/products', [SaleReturnController::class, 'products']);
        Route::post('purchase/return', [SaleReturnController::class, 'store']);

        Route::post('sales/{sale}/return', [SaleReturnController::class, 'store']);

        Route::get('reports', [ReportsController::class, 'index']);

        Route::get('settings', [SettingsController::class, 'index']);
        Route::post('settings/update', [SettingsController::class, 'createOrUpdate']);

        Route::prefix('reports/financial')->group(function () {
            Route::get('/', [FinancialReportController::class, 'summary']);
            Route::get('/products', [FinancialReportController::class, 'products']);
        });
        Route::get('sales', [SaleController::class, 'index']);
        Route::get('warehouse-inventory', [WarehouseInventoryController::class, 'index']);
        Route::get('cashier-reports', [ReportsController::class, 'cashier_reports']);
    });
    Route::get('point-of-sale/products', [ProductsController::class, 'cached_product']);
});
