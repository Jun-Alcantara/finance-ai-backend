<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreIncomeRequest extends FormRequest
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
        return [
            'source_of_income_id' => [
                'required',
                'uuid',
                Rule::exists('source_of_incomes', 'id')->where('user_id', $this->user()->id),
            ],
            'bank_account_id' => [
                'required',
                'uuid',
                Rule::exists('bank_accounts', 'id')
                    ->where('user_id', $this->user()->id)
                    ->whereNull('deleted_at'),
            ],
            'amount' => 'required|numeric|min:0.01|max:999999999999.99',
            'remarks' => 'nullable|string|max:5000',
            'date' => 'required|date',
            'received' => 'boolean',
            'is_recurring' => 'boolean',
            'recurring_type' => [
                'nullable',
                'required_if:is_recurring,true',
                Rule::in(['specific_date', 'start_of_month', 'end_of_month']),
            ],
            'recurring_day' => [
                'nullable',
                'required_if:recurring_type,specific_date',
                'integer',
                'min:1',
                'max:31',
            ],
            'recur_until' => [
                'nullable',
                'required_if:is_recurring,true',
                'date',
                'after_or_equal:date',
            ],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'source_of_income_id.exists' => 'The selected source of income does not exist or does not belong to you.',
            'bank_account_id.exists' => 'The selected bank account does not exist or does not belong to you.',
            'recurring_type.required_if' => 'The recurring type is required when income is recurring.',
            'recurring_day.required_if' => 'The recurring day is required when recurring type is specific_date.',
            'recur_until.required_if' => 'The recur until date is required when income is recurring.',
            'recur_until.after_or_equal' => 'The recur until date must be on or after the income date.',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Set default values
        if (!$this->has('received')) {
            $this->merge(['received' => false]);
        }

        if (!$this->has('is_recurring')) {
            $this->merge(['is_recurring' => false]);
        }

        // Clear recurring fields if not recurring
        if (!$this->input('is_recurring')) {
            $this->merge([
                'recurring_type' => null,
                'recurring_day' => null,
                'recur_until' => null,
            ]);
        }

        // Clear recurring_day if not specific_date type
        if ($this->input('recurring_type') !== 'specific_date') {
            $this->merge(['recurring_day' => null]);
        }
    }
}
