<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Expense extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'bank_account_id',
        'amount',
        'remarks',
        'date',
        'is_paid',
        'is_recurring',
        'recurring_type',
        'recurring_day',
        'recurring_group_id',
        'recur_until',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'amount' => 'decimal:2',
        'date' => 'date',
        'is_paid' => 'boolean',
        'is_recurring' => 'boolean',
        'recurring_day' => 'integer',
        'recur_until' => 'date',
    ];

    /**
     * Get the user that owns the expense.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the bank account (source) of the expense.
     */
    public function bankAccount()
    {
        return $this->belongsTo(BankAccount::class);
    }

    /**
     * Check if the expense can be edited.
     */
    public function canBeEdited(): bool
    {
        // Cannot edit if already paid
        return !$this->is_paid;
    }

    /**
     * Check if the expense can be deleted.
     */
    public function canBeDeleted(): bool
    {
        // Cannot delete if already paid
        return !$this->is_paid;
    }

    /**
     * Mark expense as paid and deduct from bank account balance.
     */
    public function markAsPaid(): void
    {
        if ($this->is_paid) {
            return;
        }

        $this->is_paid = true;
        $this->save();

        // Deduct from bank account balance
        $this->bankAccount->decrement('balance', $this->amount);
    }

    /**
     * Generate recurring group ID for new recurring expense.
     */
    public static function generateRecurringGroupId(): string
    {
        return (string) Str::uuid();
    }

    /**
     * Scope to get expenses for a specific user.
     */
    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope to filter by date range.
     */
    public function scopeDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('date', [$startDate, $endDate]);
    }

    /**
     * Scope to filter by paid status.
     */
    public function scopePaid($query, $isPaid = true)
    {
        return $query->where('is_paid', $isPaid);
    }
}
