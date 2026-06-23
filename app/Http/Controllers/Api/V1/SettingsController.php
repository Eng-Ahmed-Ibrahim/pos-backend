<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Setting;
use App\Helpers\Helpers;

class SettingsController extends Controller
{
    public function index()
    {
         
        return response()->json([
            "status"=>true,
            "settings"=>Helpers::cache_settings(),
        ]);
    }
    public function createOrUpdate(Request $request)
    {

        // 1. حدد هنا قائمة المفاتيح الخاصة بالملفات/الصور فقط
        $fileKeys = ['system_logo', 'invoice_logo'];

        // 2. استقبال جميع البيانات القادمة من الـ Request ديناميكياً
        $allInputs = $request->all();

        foreach ($allInputs as $key => $value) {
            if (in_array($key, $fileKeys)) {
                if ($request->hasFile($key)) {
                    $file = $request->file($key);
                    $imageName = $key.".png";
                    $file->move(public_path('uploads/settings'), $imageName);
                    $path = 'uploads/settings/' . $imageName;
                    Setting::updateOrCreate(
                        ['key' => $key],
                        ['value' => $path]
                    );
                }
            }
            // إذا كان المفتاح عبارة عن نص عادي (وليس قيمة فارغة أو نص رابط قديم)
            else {
                // نضمن عدم تخزين قيم "null" كـ نصوص إذا كانت فارغة
                Setting::updateOrCreate(
                    ['key' => $key],
                    ['value' => $value ?? '']
                );
            }
        }
        Helpers::delete_settings();

        return response()->json([
            'status' => 'success',
            'message' => 'تم تحديث الإعدادات ديناميكياً بنجاح!'
        ], 200);
    }
}
