<?php

namespace App\Helpers;

use App\Models\Category;
use App\Models\Product;
use App\Models\Setting;
use App\Models\SubCategory;
use App\Models\Supplier;
use Illuminate\Support\Facades\Cache;

class Helpers
{
    // categories
    public static function cache_categories()
    {
        return Cache::rememberForever('categories', function () {
            return Category::latest()->get()->toArray();
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
            return Supplier::latest()->get()->toArray();
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
            return SubCategory::latest()->get()->toArray();
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
            return Product::select('id', 'name', 'barcode', 'price', 'category_id','sub_category_id','stock')
                ->where("price",">",0)
                ->where('stock','>',0)
                ->get()->toArray();
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
            return Product::select('id', 'name', 'barcode', 'price', 'category_id','sub_category_id','stock')
                ->get()->toArray();
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
}
