<?php

namespace App\Http\Controllers;

use App\Models\Expense;
use App\Models\Income;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class LedgerController extends Controller
{
    /**
     * Display a listing of ledger entries (incomes and expenses).
     */
    public function index(Request $request)
    {
        $userId = $request->user()->id;
        
        $startDate = $request->input('start_date') 
            ? Carbon::parse($request->input('start_date'))->startOfDay() 
            : Carbon::now()->subDays(30)->startOfDay();
            
        $endDate = $request->input('end_date') 
            ? Carbon::parse($request->input('end_date'))->endOfDay() 
            : Carbon::now()->endOfDay();

        // Fetch Incomes (Both Realized and Pending)
        $incomes = Income::with(['sourceOfIncome', 'bankAccount'])
            ->where('user_id', $userId)
            ->whereBetween('date', [$startDate, $endDate])
            ->get()
            ->map(function ($income) {
                return [
                    'id' => $income->id,
                    'date' => $income->date->format('Y-m-d'),
                    'description' => $income->remarks ?: ($income->sourceOfIncome->name ?? 'Income'),
                    'amount' => $income->amount,
                    'type' => 'credit',
                    'status' => $income->received ? 'completed' : 'pending',
                    'category' => $income->sourceOfIncome->name ?? 'Uncategorized',
                    'account_name' => $income->bankAccount->name ?? 'Unknown Account',
                ];
            });

        // Fetch Expenses (Both Paid and Pending)
        $expenses = Expense::with(['bankAccount'])
            ->where('user_id', $userId)
            ->whereBetween('date', [$startDate, $endDate])
            ->get()
            ->map(function ($expense) {
                return [
                    'id' => $expense->id,
                    'date' => $expense->date->format('Y-m-d'),
                    'description' => $expense->remarks ?: 'Expense',
                    'amount' => $expense->amount,
                    'type' => 'debit',
                    'status' => $expense->is_paid ? 'completed' : 'pending',
                    'category' => 'Expense',
                    'account_name' => $expense->bankAccount->name ?? 'Unknown Account',
                ];
            });

        // Use concat to combine collections safely
        $ledger = $incomes->concat($expenses)->sortBy('date')->values();

        return response()->json([
            'data' => $ledger,
            'summary' => [
                'total_credit' => $incomes->sum('amount'),
                'total_debit' => $expenses->sum('amount'),
                'net_flow' => $incomes->sum('amount') - $expenses->sum('amount')
            ],
            'meta' => [
                'start_date' => $startDate->toDateString(),
                'end_date' => $endDate->toDateString(),
                'count' => $ledger->count()
            ]
        ]);
    }
}
