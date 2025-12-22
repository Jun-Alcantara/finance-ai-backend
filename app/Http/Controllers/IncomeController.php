<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreIncomeRequest;
use App\Http\Requests\UpdateIncomeRequest;
use App\Http\Resources\IncomeResource;
use App\Models\Income;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class IncomeController extends Controller
{
    /**
     * Display a listing of incomes.
     */
    public function index(Request $request)
    {
        $query = Income::with(['sourceOfIncome', 'bankAccount'])
            ->forUser($request->user()->id)
            ->orderBy('date', 'asc');

        // Apply search filter
        if ($request->has('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('remarks', 'like', "%{$search}%")
                    ->orWhereHas('sourceOfIncome', function ($q) use ($search) {
                        $q->where('name', 'like', "%{$search}%");
                    });
            });
        }

        // Apply date range filter
        if ($request->has('start_date') && $request->has('end_date')) {
            $query->dateRange($request->input('start_date'), $request->input('end_date'));
        }

        // Apply received filter
        if ($request->has('received')) {
            $query->received($request->boolean('received'));
        }

        // Apply recurring filter
        if ($request->has('is_recurring')) {
            $query->where('is_recurring', $request->boolean('is_recurring'));
        }

        $perPage = $request->input('per_page', 15);
        $incomes = $query->paginate($perPage);

        return IncomeResource::collection($incomes);
    }

    /**
     * Store a newly created income.
     */
    public function store(StoreIncomeRequest $request)
    {
        $data = $request->validated();
        $data['user_id'] = $request->user()->id;

        DB::beginTransaction();
        try {
            // Generate recurring group ID if income is recurring
            if ($data['is_recurring']) {
                $data['recurring_group_id'] = Income::generateRecurringGroupId();
                
                // Create all recurring incomes up to recur_until date
                $this->createRecurringIncomes($data);
            } else {
                // Create single income
                $income = Income::create($data);
                
                // If received, update bank account balance
                if ($income->received) {
                    $income->markAsReceived();
                }
            }

            DB::commit();

            // Return the first income (the one with the original date)
            $firstIncome = Income::where('user_id', $data['user_id'])
                ->where('recurring_group_id', $data['recurring_group_id'] ?? null)
                ->orderBy('date', 'asc')
                ->first();

            if (!$firstIncome) {
                $firstIncome = $income;
            }

            return new IncomeResource($firstIncome->load(['sourceOfIncome', 'bankAccount']));
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Create recurring incomes up to the recur_until date.
     */
    private function createRecurringIncomes(array $data): void
    {
        $startDate = Carbon::parse($data['date'])->startOfDay();
        $endDate = Carbon::parse($data['recur_until'])->endOfDay();
        
        // Adjust start date based on recurring type
        $currentDate = $this->getFirstOccurrence($startDate, $data['recurring_type'], $data['recurring_day'] ?? null);

        while ($currentDate->lte($endDate)) {
            $incomeData = $data;
            $incomeData['date'] = $currentDate->format('Y-m-d');
            
            $income = Income::create($incomeData);
            
            // If received, update bank account balance
            if ($income->received) {
                $income->markAsReceived();
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
                // Use the first day of the selected month
                $firstDate->startOfMonth();
                break;
            
            case 'end_of_month':
                // Use the last day of the selected month
                $firstDate->endOfMonth();
                break;
            
            case 'specific_date':
                // Use the specified day, handling months with fewer days
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
                // Move to start of next month
                $nextDate->addMonthNoOverflow()->startOfMonth();
                break;
            
            case 'end_of_month':
                // Move to start of next month first, then go to end
                $nextDate->addMonthNoOverflow()->endOfMonth();
                break;
            
            case 'specific_date':
                // Move to the first day of next month, then set the day
                $nextDate->addMonthNoOverflow();
                // Set to the specified day, handling months with fewer days
                $daysInMonth = $nextDate->daysInMonth;
                $day = min($recurringDay, $daysInMonth);
                $nextDate->day = $day;
                break;
        }

        return $nextDate;
    }

    /**
     * Display the specified income.
     */
    public function show(Request $request, Income $income)
    {
        // Check if the income belongs to the authenticated user
        if ($income->user_id !== $request->user()->id) {
            return response()->json([
                'message' => 'This income does not belong to you.',
            ], Response::HTTP_FORBIDDEN);
        }

        return new IncomeResource($income->load(['category', 'bankAccount']));
    }

    /**
     * Update the specified income.
     */
    public function update(UpdateIncomeRequest $request, Income $income)
    {
        // Check if the income belongs to the authenticated user
        if ($income->user_id !== $request->user()->id) {
            return response()->json([
                'message' => 'This income does not belong to you.',
            ], Response::HTTP_FORBIDDEN);
        }

        // Check if the income can be edited
        if (!$income->canBeEdited()) {
            $message = $income->received 
                ? 'Received incomes cannot be edited.' 
                : 'Only future incomes can be edited.';
            
            return response()->json([
                'message' => $message,
            ], Response::HTTP_FORBIDDEN);
        }

        $data = $request->validated();
        $applyToFuture = $data['apply_to_future'] ?? false;
        unset($data['apply_to_future']);

        DB::beginTransaction();
        try {
            // If applying to all future recurring incomes
            if ($applyToFuture && $income->is_recurring && $income->recurring_group_id) {
                // Get all future incomes in the same group
                $futureIncomes = Income::where('recurring_group_id', $income->recurring_group_id)
                    ->where('date', '>=', $income->date)
                    ->get();

                foreach ($futureIncomes as $futureIncome) {
                    $futureIncome->update($data);
                }
            } else {
                // Update only this income
                $income->update($data);
            }

            DB::commit();

            return new IncomeResource($income->fresh(['category', 'bankAccount']));
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Remove the specified income.
     */
    public function destroy(Request $request, Income $income)
    {
        // Check if the income belongs to the authenticated user
        if ($income->user_id !== $request->user()->id) {
            return response()->json([
                'message' => 'This income does not belong to you.',
            ], Response::HTTP_FORBIDDEN);
        }

        // Check if the income can be deleted
        if (!$income->canBeDeleted()) {
            $message = $income->received 
                ? 'Received incomes cannot be deleted.' 
                : 'Only future incomes can be deleted.';
            
            return response()->json([
                'message' => $message,
            ], Response::HTTP_FORBIDDEN);
        }

        $applyToFuture = $request->boolean('apply_to_future', false);

        DB::beginTransaction();
        try {
            // If applying to all future recurring incomes
            if ($applyToFuture && $income->is_recurring && $income->recurring_group_id) {
                // Delete all future incomes in the same group
                Income::where('recurring_group_id', $income->recurring_group_id)
                    ->where('date', '>=', $income->date)
                    ->delete();
            } else {
                // Delete only this income
                $income->delete();
            }

            DB::commit();

            return response()->json([
                'message' => 'Income deleted successfully.',
            ], Response::HTTP_OK);
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Mark income as received.
     */
    public function markAsReceived(Request $request, Income $income)
    {
        // Check if the income belongs to the authenticated user
        if ($income->user_id !== $request->user()->id) {
            return response()->json([
                'message' => 'This income does not belong to you.',
            ], Response::HTTP_FORBIDDEN);
        }

        if ($income->received) {
            return response()->json([
                'message' => 'Income is already marked as received.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        DB::beginTransaction();
        try {
            $income->markAsReceived();
            DB::commit();

            return new IncomeResource($income->fresh(['sourceOfIncome', 'bankAccount']));
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }
}
