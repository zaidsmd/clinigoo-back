<?php

namespace App\Http\Requests\Admin;

use App\Enums\HealthcareServiceCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreServiceRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if (! $this->has('category') && $this->has('service_category_id')) {
            $this->merge(['category' => $this->input('service_category_id')]);
        }
        if (! $this->has('category') || empty($this->input('category'))) {
            $this->merge(['category' => 'soins']);
        }
    }

    /**
     * @return array<string, array<mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'category' => ['required', Rule::in(HealthcareServiceCategory::values())],
            'description' => ['nullable', 'string'],
            'icon' => ['nullable'],
            'base_price' => ['nullable', 'numeric', 'min:0'],
            'duration_minutes' => ['nullable', 'integer', 'min:1'],
            'is_active' => ['boolean'],
            'sort_order' => ['nullable', 'integer'],
            'profession_id' => ['nullable'],
            'profession_ids' => ['nullable', 'array'],
            'professions' => ['nullable'],
        ];
    }

    public function authorize(): bool
    {
        return true;
    }
}
