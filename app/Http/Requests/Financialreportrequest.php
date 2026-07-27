<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class FinancialReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // عدّلها لو عندك policy/permission معينة
    }

    public function rules(): array
    {
        return [
            'start_at' => ['required', 'date'],
            'end_at' => ['required', 'date', 'after_or_equal:start_at'],
        ];
    }

    public function messages(): array
    {
        return [
            'start_at.required' => 'تاريخ البداية مطلوب',
            'end_at.required' => 'تاريخ النهاية مطلوب',
            'end_at.after_or_equal' => 'تاريخ النهاية لازم يكون بعد أو يساوي تاريخ البداية',
        ];
    }
}