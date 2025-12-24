<?php

namespace App\Http\Controllers;

use App\Models\Expense;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ExpenseController extends Controller
{
    /**
     * Display a listing of expenses.
     */
    /**
     * Display a listing of expenses.
     */
    public function index(Request $request)
    {
        $query = Expense::with(['bankAccount'])
            ->forUser($request->user()->id)
            ->orderBy('due_date', 'asc'); // Default sort by due date

        // Apply search filter
        if ($request->has('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('remarks', 'like', "%{$search}%");
            });
        }

        // Apply date range filter
        if ($request->has('start_date') && $request->has('end_date')) {
            $query->dateRange($request->input('start_date'), $request->input('end_date'));
        }

        // Apply paid filter
        if ($request->has('is_paid')) {
            $query->paid($request->boolean('is_paid'));
        }

        // Apply recurring filter
        if ($request->has('is_recurring')) {
            $query->where('is_recurring', $request->boolean('is_recurring'));
        }

        $perPage = $request->input('per_page', 15);
        $expenses = $query->paginate($perPage);

        return response()->json($expenses);
    }

    /**
     * Store a newly created expense.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'bank_account_id' => 'required|exists:bank_accounts,id',
            'amount' => 'required|numeric|min:0.01',
            'remarks' => 'nullable|string|max:255',
            'due_date' => 'required|date',
            'is_paid' => 'boolean',
            'payment_date' => 'nullable|required_if:is_paid,true|date',
            'is_recurring' => 'boolean',
            'recurring_type' => 'nullable|required_if:is_recurring,true|in:start_of_month,end_of_month,specific_date',
            'recurring_day' => 'nullable|required_if:recurring_type,specific_date|integer|min:1|max:31',
            'recur_until' => 'nullable|required_if:is_recurring,true|date|after:due_date',
        ]);

        $data['user_id'] = $request->user()->id;
        
        // Capture is_paid intent but ensure we create as unpaid first to trigger markAsPaid logic properly
        $shouldBePaid = $data['is_paid'] ?? false;
        $data['is_paid'] = false;
        
        // Remove payment_date from initial creation data if we are going to use markAsPaid
        // Actually markAsPaid sets it.
        $paymentDate = $data['payment_date'] ?? null;
        unset($data['payment_date']);
        
        $data['is_recurring'] = $data['is_recurring'] ?? false;

        DB::beginTransaction();
        try {
            // Generate recurring group ID if expense is recurring
            if ($data['is_recurring']) {
                $data['recurring_group_id'] = Expense::generateRecurringGroupId();
                
                // Create all recurring expenses up to recur_until date
                $this->createRecurringExpenses($data, $shouldBePaid, $paymentDate);
            } else {
                // Create single expense
                $expense = Expense::create($data);
                
                // If paid, update bank account balance (deduct)
                if ($shouldBePaid) {
                    $expense->markAsPaid($paymentDate);
                }
            }

            DB::commit();

            // Return the first expense (the one with the original date)
            $firstExpense = Expense::where('user_id', $data['user_id'])
                ->where('recurring_group_id', $data['recurring_group_id'] ?? null)
                ->orderBy('due_date', 'asc')
                ->first();

            if (!$firstExpense) {
               // Fallback mechanism to ensure we return something recently created
                if (isset($expense)) {
                    $firstExpense = $expense;
                } else {
                     $firstExpense = Expense::where('user_id', $data['user_id'])
                        ->latest()
                        ->first();
                }
            }
            
            return response()->json($firstExpense->load(['bankAccount']), 201);
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Create recurring expenses up to the recur_until date.
     */
    private function createRecurringExpenses(array $data, bool $shouldBePaid, ?string $paymentDate = null): void
    {
        $startDate = Carbon::parse($data['due_date'])->startOfDay();
        $endDate = Carbon::parse($data['recur_until'])->endOfDay();
        
        // Adjust start date based on recurring type
        $currentDate = $this->getFirstOccurrence($startDate, $data['recurring_type'], $data['recurring_day'] ?? null);

        while ($currentDate->lte($endDate)) {
            $expenseData = $data;
            $expenseData['due_date'] = $currentDate->format('Y-m-d');
            
            $expense = Expense::create($expenseData);
            
            // If paid, deduct from bank account
            if ($shouldBePaid) {
                // For recurring, if we mark as paid, should we assume same payment date? 
                // Usually recurring expenses are future, so auto-pay is tricky. 
                // But if user says "Paid", we pay them. 
                // Maybe payment_date should only apply to the FIRST one? 
                // Or maybe payment_date implies "Paid on X". If I create 10 past expenses, maybe.
                // If I create future expenses, they probably shouldn't be paid yet?
                // Assuming user knows what they are doing.
                $expense->markAsPaid($paymentDate);
            }

            // Calculate next occurrence
            $currentDate = $this->getNextOccurrence($currentDate, $data['recurring_type'], $data['recurring_day'] ?? null);
        }
    }

    /**
     * Get the first occurrence date based on recurring type.
     */
    private function getFirstOccurrence(Carbon $date, string $recurringType, ?int $recurringDay): Carbon
    {
        $firstDate = $date->copy();

        switch ($recurringType) {
            case 'start_of_month':
                $firstDate->startOfMonth();
                break;
            case 'end_of_month':
                $firstDate->endOfMonth();
                break;
            case 'specific_date':
                $daysInMonth = $firstDate->daysInMonth;
                $day = min($recurringDay, $daysInMonth);
                $firstDate->day = $day;
                break;
        }

        return $firstDate;
    }

    /**
     * Calculate the next occurrence date based on recurring type.
     */
    private function getNextOccurrence(Carbon $currentDate, string $recurringType, ?int $recurringDay): Carbon
    {
        $nextDate = $currentDate->copy();

        switch ($recurringType) {
            case 'start_of_month':
                $nextDate->addMonthNoOverflow()->startOfMonth();
                break;
            case 'end_of_month':
                $nextDate->addMonthNoOverflow()->endOfMonth();
                break;
            case 'specific_date':
                $nextDate->addMonthNoOverflow();
                $daysInMonth = $nextDate->daysInMonth;
                $day = min($recurringDay, $daysInMonth);
                $nextDate->day = $day;
                break;
        }

        return $nextDate;
    }

    /**
     * Display the specified expense.
     */
    public function show(Request $request, Expense $expense)
    {
        if ($expense->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        return response()->json($expense->load(['bankAccount']));
    }

    /**
     * Update the specified expense.
     */
    public function update(Request $request, Expense $expense)
    {
        if ($expense->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        if (!$expense->canBeEdited()) {
            return response()->json(['message' => 'Paid expenses cannot be edited.'], 403);
        }

        $data = $request->validate([
            'bank_account_id' => 'exists:bank_accounts,id',
            'amount' => 'numeric|min:0.01',
            'remarks' => 'nullable|string|max:255',
            'due_date' => 'date',
            'is_paid' => 'boolean', 
            'payment_date' => 'nullable|required_if:is_paid,true|date',
            'apply_to_future' => 'boolean'
        ]);

        $applyToFuture = $data['apply_to_future'] ?? false;
        unset($data['apply_to_future']);

        DB::beginTransaction();
        try {
            // Check if marking as paid
            $shouldBePaid = isset($data['is_paid']) && $data['is_paid'] === true && !$expense->is_paid;
            $paymentDate = $data['payment_date'] ?? null;
            
            if ($shouldBePaid) {
                $data['is_paid'] = false; // Prevent fill from setting is_paid=true directly
                unset($data['payment_date']); // Handled by markAsPaid
            }

            if ($applyToFuture && $expense->is_recurring && $expense->recurring_group_id) {
                // Get all future expenses
                $futureExpenses = Expense::where('recurring_group_id', $expense->recurring_group_id)
                    ->where('due_date', '>=', $expense->due_date)
                    ->get();

                foreach ($futureExpenses as $futureExpense) {
                    // Skip if already paid (can't edit paid ones)
                    if ($futureExpense->is_paid) continue;

                    $futureExpense->fill($data);
                    
                    if ($shouldBePaid) {
                        $futureExpense->save(); // Save details first as unpaid
                        $futureExpense->markAsPaid($paymentDate); // Then mark as paid
                    } else {
                        $futureExpense->save();
                    }
                }
            } else {
                $expense->fill($data);
                if ($shouldBePaid) {
                    $expense->save();
                    $expense->markAsPaid($paymentDate);
                } else {
                    $expense->save();
                }
            }

            DB::commit();

            return response()->json($expense->fresh(['bankAccount']));
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Remove the specified expense.
     */
    public function destroy(Request $request, Expense $expense)
    {
        if ($expense->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        if (!$expense->canBeDeleted()) {
            return response()->json(['message' => 'Paid expenses cannot be deleted.'], 403);
        }

        $applyToFuture = $request->boolean('apply_to_future', false);

        DB::beginTransaction();
        try {
            if ($applyToFuture && $expense->is_recurring && $expense->recurring_group_id) {
                // Delete all future, UNPAID expenses
                Expense::where('recurring_group_id', $expense->recurring_group_id)
                    ->where('due_date', '>=', $expense->due_date)
                    ->where('is_paid', false) // Safety check: only delete unpaid
                    ->delete();
            } else {
                $expense->delete();
            }

            DB::commit();

            return response()->json(['message' => 'Expense deleted successfully.']);
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Mark expense as paid.
     */
    public function markAsPaid(Request $request, Expense $expense)
    {
        if ($expense->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        if ($expense->is_paid) {
            return response()->json(['message' => 'Expense is already marked as paid.'], 422);
        }

        $data = $request->validate([
            'payment_date' => 'required|date',
        ]);

        DB::beginTransaction();
        try {
            $expense->markAsPaid($data['payment_date']);
            DB::commit();

            return response()->json($expense->fresh(['bankAccount']));
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }
}
