<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreBankAccountRequest;
use App\Http\Requests\UpdateBankAccountRequest;
use App\Http\Resources\BankAccountResource;
use App\Models\BankAccount;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class BankAccountController extends Controller
{
    /**
     * Display a paginated listing of bank accounts.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $perPage = $request->input('per_page', 15);
        $search = $request->input('search');

        $query = BankAccount::where('user_id', auth()->id());

        // Apply search filter if provided
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', '%' . $search . '%')
                  ->orWhere('account_number', 'like', '%' . $search . '%');
            });
        }

        $bankAccounts = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return BankAccountResource::collection($bankAccounts);
    }

    /**
     * Store a newly created bank account in storage.
     */
    public function store(StoreBankAccountRequest $request): BankAccountResource
    {
        $bankAccount = BankAccount::create([
            'user_id' => auth()->id(),
            ...$request->validated(),
        ]);

        return new BankAccountResource($bankAccount);
    }

    /**
     * Display the specified bank account.
     */
    public function show(BankAccount $bankAccount): BankAccountResource
    {
        // Ensure the bank account belongs to the authenticated user
        if ($bankAccount->user_id !== auth()->id()) {
            abort(403, 'This bank account does not belong to you.');
        }

        return new BankAccountResource($bankAccount);
    }

    /**
     * Update the specified bank account in storage.
     */
    public function update(UpdateBankAccountRequest $request, BankAccount $bankAccount): BankAccountResource
    {
        // Ensure the bank account belongs to the authenticated user
        if ($bankAccount->user_id !== auth()->id()) {
            abort(403, 'This bank account does not belong to you.');
        }

        $bankAccount->update($request->validated());

        return new BankAccountResource($bankAccount);
    }

    /**
     * Remove the specified bank account from storage (soft delete).
     */
    public function destroy(BankAccount $bankAccount): Response
    {
        // Ensure the bank account belongs to the authenticated user
        if ($bankAccount->user_id !== auth()->id()) {
            abort(403, 'This bank account does not belong to you.');
        }

        $bankAccount->delete();

        return response()->json([
            'message' => 'Bank account deleted successfully.',
        ], 200);
    }
}
