<?php

namespace App\Helpers;

use App\Models\Product;
use App\Models\Setting;
use App\Models\Category;
use App\Models\Supplier;
use App\Models\SubCategory;
use App\Models\Unit;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class Helpers
{
    // categories
    public static function cache_categories()
    {
        return Cache::rememberForever('categories', function () {
            return DB::table('categories')
                ->orderByDesc('created_at')
                ->get()
                ->toArray();
        });
    }

    public static function delete_categories()
    {
        Cache::forget('categories');
        return  self::cache_categories();
    }
    // suppliers
    public static function cache_suppliers()
    {
        return Cache::rememberForever('suppliers', function () {
            return DB::table('suppliers')
                ->orderByDesc('created_at')
                ->get()
                ->toArray();
        });
    }
    public static function delete_suppliers()
    {
        Cache::forget('suppliers');
        return  self::cache_suppliers();
    }
    // sub categories
    public static function cache_sub_categories()
    {
        return Cache::rememberForever('sub_categories', function () {
            return DB::table('sub_categories')
                ->orderByDesc('created_at')
                ->get()
                ->toArray();
        });
    }
    public static function delete_sub_categories()
    {
        Cache::forget('sub_categories');
        return  self::cache_sub_categories();
    }
    // products
    public static function cache_products()
    {
        return Cache::rememberForever('products', function () {
            return DB::table('products')
                ->select(
                    'products.id',
                    'products.name',
                    'products.barcode',
                    'products.price',
                    'products.category_id',
                    'products.sub_category_id'
                )
                ->where('products.price', '>', 0)
                ->whereExists(function ($query) {
                    $query->select(DB::raw(1))
                        ->from('purchase_items')
                        ->whereColumn('purchase_items.product_id', 'products.id')
                        ->where('purchase_items.remaining_stock', '>', 0);
                })
                ->get()
                ->toArray();
        });
    }
    public static function delete_products()
    {
        Cache::forget('products');
        return  self::cache_products();
    }
    // cache all products
    public static function cache_all_products()
    {
        return Cache::rememberForever('all_products', function () {
            return DB::table('products')
                ->leftJoin('units', 'units.id', '=', 'products.unit_id')
                ->select(
                    'products.id',
                    'products.name',
                    'products.barcode',
                    'products.price',
                    // 'products.category_id',
                    // 'products.sub_category_id',
                    'products.unit_id',
                    'units.name as unit_name'
                )
                ->get()
                ->toArray();
        });
    }
    public static function delete_all_products()
    {
        Cache::forget('all_products');
        return  self::cache_all_products();
    }
    // settings
    public static function cache_settings()
    {
        return Cache::rememberForever('settings', function () {
            return Setting::pluck('value', 'key')->toArray();
        });
    }
    public static function delete_settings()
    {
        Cache::forget('settings');
        return  self::cache_settings();
    }
    // units
    public static function cache_units()
    {
        return Cache::rememberForever('units', function () {
            return Unit::latest()->get()->toArray();
        });
    }
    public static function delete_units()
    {
        Cache::forget('units');
        return  self::cache_units();
    }
}
