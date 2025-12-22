<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BankAccountController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\SourceOfIncomeController;
use App\Http\Controllers\IncomeController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\LedgerController;

// Public routes
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

// Protected routes
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'user']);
    
    // Category routes
    Route::apiResource('categories', CategoryController::class);

    // Source of Income routes
    Route::apiResource('source-of-incomes', SourceOfIncomeController::class);
    
    // Bank Account routes
    Route::apiResource('bank-accounts', BankAccountController::class);
    
    // Income routes
    Route::apiResource('incomes', IncomeController::class);
    Route::post('incomes/{income}/mark-as-received', [IncomeController::class, 'markAsReceived']);

    // Expense routes
    Route::apiResource('expenses', ExpenseController::class);
    Route::post('expenses/{expense}/mark-as-paid', [ExpenseController::class, 'markAsPaid']);

    // Ledger routes
    Route::get('/ledger', [LedgerController::class, 'index']);
});
