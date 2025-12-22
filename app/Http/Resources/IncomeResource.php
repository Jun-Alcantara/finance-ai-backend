<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class IncomeResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'source_of_income_id' => $this->source_of_income_id,
            'source_of_income' => [
                'id' => $this->sourceOfIncome->id,
                'name' => $this->sourceOfIncome->name,
            ],
            'bank_account_id' => $this->bank_account_id,
            'bank_account' => [
                'id' => $this->bankAccount->id,
                'name' => $this->bankAccount->name,
                'account_number' => $this->bankAccount->account_number,
            ],
            'amount' => $this->amount,
            'remarks' => $this->remarks,
            'date' => $this->date->format('Y-m-d'),
            'received' => $this->received,
            'is_recurring' => $this->is_recurring,
            'recurring_type' => $this->recurring_type,
            'recurring_day' => $this->recurring_day,
            'recurring_group_id' => $this->recurring_group_id,
            'recur_until' => $this->recur_until ? $this->recur_until->format('Y-m-d') : null,
            'can_edit' => $this->canBeEdited(),
            'can_delete' => $this->canBeDeleted(),
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
        ];
    }
}
