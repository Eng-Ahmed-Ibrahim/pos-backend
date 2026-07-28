<?php

namespace App\Http\Requests;


use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\Exceptions\HttpResponseException;

class StoreProductRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'barcode' => 'required|string|unique:products,barcode',
            'category_id' => 'required|exists:categories,id',
            'sub_category_id' => 'nullable|exists:sub_categories,id',
            'minimum_stock' => 'nullable|integer',
            'price' => 'nullable|decimal:0,3',
            'unit_id' => 'required|integer|exists:units,id',
        ];
    }
    public function messages(): array
    {
        return  [
            'name.required' => 'اسم المنتج مطلوب.',
            'name.string' => 'اسم المنتج يجب أن يكون نصًا.',
            'name.max' => 'اسم المنتج يجب ألا يزيد عن 255 حرفًا.',

            'barcode.required' => 'الباركود مطلوب.',
            'barcode.string' => 'الباركود يجب أن يكون نصًا.',
            'barcode.unique' => 'الباركود موجود بالفعل.',

            'category_id.required' => 'التصنيف مطلوب.',
            'category_id.exists' => 'التصنيف المحدد غير موجود.',

            'sub_category_id.exists' => 'التصنيف الفرعي المحدد غير موجود.',

            'minimum_stock.integer' => 'الحد الأدنى للمخزون يجب أن يكون رقمًا صحيحًا.',

            'price.integer' => 'السعر يجب أن يكون رقمًا صحيحًا.',

            'unit_id.required' => 'الوحدة مطلوبة.',
            'unit_id.integer' => 'الوحدة المحددة غير صحيحة.',
            'unit_id.exists' => 'الوحدة المحددة غير موجودة.',
        ];
    }
    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json([
            'status' => false,
            'message' => $validator->errors()->first(),
            'errors' => $validator->errors(),
        ], 422));
    }
}
