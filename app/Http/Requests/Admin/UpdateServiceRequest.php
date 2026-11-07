<?php

namespace App\Http\Requests\Admin;

use App\Enums\HealthcareServiceCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateServiceRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if (! $this->has('category') && $this->has('service_category_id')) {
            $this->merge(['category' => $this->input('service_category_id')]);
        }
    }

    /**
     * @return array<string, array<mixed>>
     */
    public function rules(): array
    {
        $rules = [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'category' => ['sometimes', 'required', Rule::in(HealthcareServiceCategory::values())],
            'description' => ['nullable', 'string'],
            'base_price' => ['nullable', 'numeric', 'min:0'],
            'duration_minutes' => ['nullable', 'integer', 'min:1'],
            'is_active' => ['boolean'],
            'sort_order' => ['nullable', 'integer'],
            'profession_id' => ['nullable'],
            'profession_ids' => ['nullable', 'array'],
            'professions' => ['nullable'],
        ];

        if ($this->hasFile('icon')) {
            $rules['icon'] = ['nullable', 'file', 'mimes:png,svg,jpg,jpeg,webp', 'max:2048'];
        } else {
            $rules['icon'] = ['nullable'];
        }

        return $rules;
    }

    public function authorize(): bool
    {
        return true;
    }
}
