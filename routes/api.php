<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CategoryController;
use App\Http\Controllers\Api\V1\ProductsController;
use App\Http\Controllers\Api\V1\PurchaseController;
use App\Http\Controllers\Api\V1\SaleController;
use App\Http\Controllers\Api\V1\SubCategoryController;
use App\Http\Controllers\Api\V1\SupplierController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;


Route::prefix('v1')->group(function () {

    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login'])->name('login');

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/user', [AuthController::class, 'user']);

        Route::apiResource('categories', CategoryController::class);

        Route::apiResource('sub-categories', SubCategoryController::class);

        Route::apiResource('products', ProductsController::class);

        Route::apiResource('suppliers', SupplierController::class);
        Route::apiResource('purchases', PurchaseController::class);
        Route::get('/purchase/create-page', [PurchaseController::class, 'create']);
        Route::post('sales', [SaleController::class, 'store']);
        Route::get('sales/{id}', [SaleController::class, 'show']);
    });
});
