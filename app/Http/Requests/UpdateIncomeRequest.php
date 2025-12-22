<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateIncomeRequest extends FormRequest
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
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rules = [
            'source_of_income_id' => [
                'sometimes',
                'uuid',
                Rule::exists('source_of_incomes', 'id')->where('user_id', $this->user()->id),
            ],
            'bank_account_id' => [
                'sometimes',
                'uuid',
                Rule::exists('bank_accounts', 'id')
                    ->where('user_id', $this->user()->id)
                    ->whereNull('deleted_at'),
            ],
            'amount' => 'sometimes|numeric|min:0.01|max:999999999999.99',
            'remarks' => 'nullable|string|max:5000',
            'date' => 'sometimes|date',
            'received' => 'sometimes|boolean',
            'is_recurring' => 'sometimes|boolean',
            'recurring_type' => [
                'nullable',
                Rule::in(['specific_date', 'start_of_month', 'end_of_month']),
            ],
            'recurring_day' => [
                'nullable',
                'integer',
                'min:1',
                'max:31',
            ],
            'recur_until' => [
                'nullable',
                'date',
            ],
            'apply_to_future' => 'sometimes|boolean', // Whether to apply changes to all future recurring incomes
        ];

        return $rules;
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'source_of_income_id.exists' => 'The selected source of income does not exist or does not belong to you.',
            'bank_account_id.exists' => 'The selected bank account does not exist or does not belong to you.',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Set default for apply_to_future
        if (!$this->has('apply_to_future')) {
            $this->merge(['apply_to_future' => false]);
        }

        // Clear recurring_day if not specific_date type
        if ($this->has('recurring_type') && $this->input('recurring_type') !== 'specific_date') {
            $this->merge(['recurring_day' => null]);
        }

        // Clear recurring fields if is_recurring is set to false
        if ($this->has('is_recurring') && !$this->input('is_recurring')) {
            $this->merge([
                'recurring_type' => null,
                'recurring_day' => null,
            ]);
        }
    }
}
