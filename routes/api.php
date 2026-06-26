<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CategoryController;
use App\Http\Controllers\Api\V1\ProductImportController;
use App\Http\Controllers\Api\V1\ProductsController;
use App\Http\Controllers\Api\V1\PurchaseController;
use App\Http\Controllers\Api\V1\ReportsController;
use App\Http\Controllers\Api\V1\RolesController;
use App\Http\Controllers\Api\V1\SaleController;
use App\Http\Controllers\Api\V1\SaleReturnController;
use App\Http\Controllers\Api\V1\SettingsController;
use App\Http\Controllers\Api\V1\SubCategoryController;
use App\Http\Controllers\Api\V1\SupplierController;
use App\Http\Controllers\Api\V1\UsersController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;


Route::prefix('v1')->group(function () {
    Route::post('/products/import', [ProductImportController::class, 'import']);

    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/user', [AuthController::class, 'user']);

        Route::apiResource('categories', CategoryController::class);

        Route::apiResource('sub-categories', SubCategoryController::class);

        Route::apiResource('products', ProductsController::class);
        Route::get('point-of-sale/products', [ProductsController::class,'cached_product']);

        Route::apiResource('suppliers', SupplierController::class);
        Route::apiResource('purchases', PurchaseController::class);
        Route::apiResource('users', UsersController::class);
        Route::apiResource('roles', RolesController::class);
        Route::get('/purchase/create-page', [PurchaseController::class, 'create']);
        Route::post('sales', [SaleController::class, 'store']);
        Route::get('sales/{id}', [SaleController::class, 'show']);

        Route::get('sales/{id}', [SaleReturnController::class, 'showSale']);
        Route::post('sales/{sale}/return', [SaleReturnController::class, 'store']);

        Route::get('reports',[ReportsController::class,'index']);

        Route::get('settings',[SettingsController::class,'index']);
        Route::post('settings/update',[SettingsController::class,'createOrUpdate']);
    });
    Route::get('sales', [SaleController::class, 'index']);
});
