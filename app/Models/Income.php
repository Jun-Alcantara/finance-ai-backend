<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Income extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'source_of_income_id',
        'bank_account_id',
        'amount',
        'remarks',
        'date',
        'received',
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
        'received' => 'boolean',
        'is_recurring' => 'boolean',
        'recurring_day' => 'integer',
        'recur_until' => 'date',
    ];

    /**
     * Get the user that owns the income.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the source of income.
     */
    public function sourceOfIncome()
    {
        return $this->belongsTo(SourceOfIncome::class);
    }

    /**
     * Get the bank account (destination) of the income.
     */
    public function bankAccount()
    {
        return $this->belongsTo(BankAccount::class);
    }

    /**
     * Check if the income is in the future.
     */
    public function isFuture(): bool
    {
        return $this->date->isFuture();
    }

    /**
     * Check if the income can be edited.
     */
    public function canBeEdited(): bool
    {
        return $this->isFuture() && !$this->received;
    }

    /**
     * Check if the income can be deleted.
     */
    public function canBeDeleted(): bool
    {
        return $this->isFuture() && !$this->received;
    }

    /**
     * Get all future incomes in the same recurring group.
     */
    public function futureRecurringIncomes()
    {
        if (!$this->is_recurring || !$this->recurring_group_id) {
            return collect([]);
        }

        return static::where('recurring_group_id', $this->recurring_group_id)
            ->where('date', '>=', $this->date)
            ->where('id', '!=', $this->id)
            ->orderBy('date')
            ->get();
    }

    /**
     * Mark income as received and update bank account balance.
     */
    public function markAsReceived(): void
    {
        if ($this->received) {
            return;
        }

        $this->received = true;
        $this->save();

        // Update bank account balance
        $this->bankAccount->increment('balance', $this->amount);
    }

    /**
     * Generate recurring group ID for new recurring income.
     */
    public static function generateRecurringGroupId(): string
    {
        return (string) Str::uuid();
    }

    /**
     * Scope to get incomes for a specific user.
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
     * Scope to filter by received status.
     */
    public function scopeReceived($query, $received = true)
    {
        return $query->where('received', $received);
    }
}
