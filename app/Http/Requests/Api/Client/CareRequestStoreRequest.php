<?php

namespace App\Http\Requests\Api\Client;

use Illuminate\Foundation\Http\FormRequest;

class CareRequestStoreRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Prepare inputs for validation.
     */
    protected function prepareForValidation(): void
    {
        $serviceId = $this->input('serviceId') ?? $this->input('service_id');
        $beneficiaryId = $this->input('beneficiaryId') ?? $this->input('beneficiary_id');
        $addressId = $this->input('addressId') ?? $this->input('address_id');
        // $durationHours = $this->input('durationHours') ?? $this->input('duration_hours');
    // $freeMobility = $this->input('free_mobility') ?? $this->input('freeMobility');
        $urgent = $this->input('urgent');
        $description = $this->input('description') ?? $this->input('notes');

        $date = $this->input('date');
        if ($date === '' || $date === 'null') {
            $date = null;
        }

        $time = $this->input('time');
        if ($time === '' || $time === 'null') {
            $time = null;
        }

        $evaluation = $this->input('evaluation');
        if ($evaluation === '' || $evaluation === 'null') {
            $evaluation = null;
        }

        $this->merge([
            'service_id' => $serviceId !== null ? (int) $serviceId : null,
            'beneficiary_id' => $beneficiaryId !== null ? (int) $beneficiaryId : null,
            'address_id' => $addressId !== null ? (int) $addressId : null,
            'urgent' => $urgent !== null ? filter_var($urgent, FILTER_VALIDATE_BOOLEAN) : null,
            'description' => $description,
            'evaluation' => null ,
            'notes' => $description,
            'date' => $date,
            'time' => $time,
            'evaluation' => $evaluation !== null ? (float) $evaluation : null,
            'gender' => $this->input('gender') ?? 'all',
        ]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'service_id' => ['required', 'integer', 'exists:services,id'],
            'beneficiary_id' => ['required', 'integer', 'exists:beneficiaries,id'],
            'address_id' => ['nullable', 'integer', 'exists:addresses,id'],
            'address' => ['nullable', 'array'],
            'address.latitude' => ['required_with:address'],
            'address.longitude' => ['required_with:address'],
            'urgent' => ['required', 'boolean'],
            'date' => ['nullable', 'date'],
            'time' => ['nullable', 'string'],
            // 'duration_hours' => ['required', 'numeric', 'min:0'],
            'description' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
            'documents' => ['nullable', 'array'],
            'documents.*' => ['nullable'],
            // 'perimeter' => ['required', 'integer', 'min:0'],
            'evaluation' => ['nullable', 'numeric', 'min:0', 'max:5'],
            'gender' => ['required', 'string', 'in:all,male,female'],
            // 'certified' => ['required', 'boolean'],
            // 'free_mobility' => ['required', 'boolean'],
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $urgent = filter_var($this->input('urgent'), FILTER_VALIDATE_BOOLEAN);

            if ($urgent) {
                if ($this->input('date') !== null) {
                    $validator->errors()->add('date', 'The date field must be null when urgent is true.');
                }
                if ($this->input('time') !== null) {
                    $validator->errors()->add('time', 'The time field must be null when urgent is true.');
                }
            }
            if (empty($this->input('address_id')) && empty($this->input('address'))) {
                $validator->errors()->add('address_id', 'Either addressId or address object (latitude and longitude) must be provided.');
            }
        });
    }
}
