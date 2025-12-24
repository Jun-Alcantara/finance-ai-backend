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
        'due_date',
        'payment_date',
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
        'due_date' => 'date',
        'payment_date' => 'date',
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
    public function markAsPaid($paymentDate = null): void
    {
        if ($this->is_paid) {
            return;
        }

        $this->is_paid = true;
        // If payment date is provided, use it, otherwise use current date if not already set?
        // Actually, let's allow passing a date. if not passed, default to now or due_date? 
        // User said: "which leads to the second field "Date of Payment" this is the actual date of payment"
        // In markAsPaid, we should likely set it.
        $this->payment_date = $paymentDate ?? now(); 
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
     * Uses payment_date if paid, otherwise due_date.
     */
    public function scopeDateRange($query, $startDate, $endDate)
    {
        // We want to filter where the "Effective Date" is within range.
        // Effective Date = COALESCE(payment_date, due_date)
        return $query->whereRaw('COALESCE(payment_date, due_date) BETWEEN ? AND ?', [$startDate, $endDate]);
    }

    /**
     * Scope to filter by paid status.
     */
    public function scopePaid($query, $isPaid = true)
    {
        return $query->where('is_paid', $isPaid);
    }
}
