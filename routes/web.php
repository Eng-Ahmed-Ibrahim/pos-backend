<?php

use App\Models\Product;
use Carbon\Carbon;
use App\Models\PurchaseItems;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return "Hello world";
});
Route::get('/1', function () {

    DB::transaction(function () {

        $product = Product::where('id', 95)
            ->lockForUpdate()
            ->first();

        sleep(20);

        $product->price += 1;
        $product->save();
    });

    return 'Done';
});

Route::get('/2', function () {
    $product = Product::where("id", "95")->first();
    return $product->price;
})->name('signup');

Route::get('/test', function () {
    return view('test', ['title' => "Hello world"]);
});
Route::get('/login', function () {
    return view('login');
});

Route::post('/login', function (Request $request) {
    $request->validate([
        "email" => "required|string|email|max:255",
        "password" => "required"
    ]);
        $credentials = $request->only('email', 'password');

    if (Auth::attempt($credentials)) {
        $request->session()->regenerate();
        return redirect()->intended('/dashboard');
    }

    return back()->withErrors([
        'message' => 'The provided credentials do not match our records.',
    ]);
});
Route::post('/logout', function (Request $request)
{
    Auth::logout();

    $request->session()->invalidate();
    $request->session()->regenerateToken();

    return redirect('/login');
});
Route::post('/register', function (Request $request) {
    $request->validate([
        "name" => "required|string|max:255",
        "email" => "required|string|email|max:255|unique:users",
        "password" => "required|min:8|string|confirmed"
    ]);
});
Route::get('/register', function () {
    return view('register');
})->name('register');
