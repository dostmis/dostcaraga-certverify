<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class AnalyticsDataRequest extends FormRequest
{
    public const GENDERS = ['Male', 'Female', 'Unknown'];

    public const AGE_BANDS = ['<18', '18-24', '25-34', '35-44', '45-59', '60+', 'Unknown'];

    /** The only query keys the endpoint will ever accept. */
    public const ALLOWED_KEYS = [
        'date_from', 'date_to', 'program', 'province', 'municipality', 'gender', 'age_band',
    ];

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'program' => ['nullable', 'string', 'max:255'],
            'province' => ['nullable', 'integer'],
            'municipality' => ['nullable', 'integer'],
            'gender' => ['nullable', 'string', 'in:'.implode(',', self::GENDERS)],
            'age_band' => ['nullable', 'string', 'in:'.implode(',', self::AGE_BANDS)],
        ];
    }

    /**
     * Reject any dimension that is not on the whitelist — unknown filters are a
     * validation error, not a silently ignored parameter.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v): void {
            $unknown = array_diff(array_keys($this->query()), self::ALLOWED_KEYS);
            if ($unknown !== []) {
                $v->errors()->add('filters', 'Unknown filter(s): '.implode(', ', $unknown));
            }
        });
    }

    /**
     * This is a JSON-only data endpoint, so validation failures always return a
     * 422 JSON body rather than redirecting (the default for GET/web requests).
     */
    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            response()->json([
                'message' => 'Invalid analytics filters.',
                'errors' => $validator->errors(),
            ], 422)
        );
    }
}
