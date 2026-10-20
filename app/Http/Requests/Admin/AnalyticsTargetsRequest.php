<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class AnalyticsTargetsRequest extends FormRequest
{
    /** Targets are a planning instrument; only the RD sets them. */
    public function authorize(): bool
    {
        return (bool) $this->user()?->isRegionalDirector();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'period_year' => ['required', 'integer', 'min:2000', 'max:2100'],

            'rows' => ['present', 'array', 'max:200'],
            'rows.*.program_code' => ['required', 'string', 'max:255'],
            // Nullable on purpose: clearing an input means "no target", which
            // is different from a target of zero.
            'rows.*.target_certificates' => ['nullable', 'integer', 'min:0', 'max:10000000'],
            'rows.*.target_people' => ['nullable', 'integer', 'min:0', 'max:10000000'],
            'rows.*.notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'rows.*.program_code.required' => 'Every target row needs a program.',
            'rows.*.target_certificates.min' => 'Targets cannot be negative.',
            'rows.*.target_people.min' => 'Targets cannot be negative.',
        ];
    }

    /** Blank number inputs arrive as '' and must be stored as NULL, not 0. */
    protected function prepareForValidation(): void
    {
        $rows = $this->input('rows');

        if (! is_array($rows)) {
            return;
        }

        foreach ($rows as $i => $row) {
            foreach (['target_certificates', 'target_people', 'notes'] as $field) {
                if (isset($row[$field]) && trim((string) $row[$field]) === '') {
                    $rows[$i][$field] = null;
                }
            }
            if (isset($row['program_code'])) {
                $rows[$i]['program_code'] = trim((string) $row['program_code']);
            }
        }

        $this->merge(['rows' => $rows]);
    }
}
