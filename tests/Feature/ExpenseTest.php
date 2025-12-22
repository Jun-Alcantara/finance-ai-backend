<?php

namespace Tests\Feature;

use App\Models\BankAccount;
use App\Models\Expense;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class ExpenseTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_unpaid_expense_without_affecting_balance()
    {
        $user = User::factory()->create();
        $bankAccount = BankAccount::factory()->create(['user_id' => $user->id, 'balance' => 1000]);

        $response = $this->actingAs($user)->postJson('/api/expenses', [
            'bank_account_id' => $bankAccount->id,
            'amount' => 100,
            'date' => '2025-01-01',
            'is_paid' => false,
        ]);

        $response->assertStatus(201);
        
        $this->assertDatabaseHas('expenses', [
            'amount' => 100,
            'is_paid' => false,
        ]);

        $this->assertEquals(1000, $bankAccount->fresh()->balance);
    }

    public function test_creating_paid_expense_deducts_balance()
    {
        $user = User::factory()->create();
        $bankAccount = BankAccount::factory()->create(['user_id' => $user->id, 'balance' => 1000]);

        $response = $this->actingAs($user)->postJson('/api/expenses', [
            'bank_account_id' => $bankAccount->id,
            'amount' => 100,
            'date' => '2025-01-01',
            'is_paid' => true,
        ]);

        $response->assertStatus(201);
        
        $this->assertDatabaseHas('expenses', [
            'amount' => 100,
            'is_paid' => true,
        ]);

        $this->assertEquals(900, $bankAccount->fresh()->balance);
    }

    public function test_marking_expense_as_paid_deducts_balance()
    {
        $user = User::factory()->create();
        $bankAccount = BankAccount::factory()->create(['user_id' => $user->id, 'balance' => 1000]);
        $expense = Expense::create([
            'user_id' => $user->id,
            'bank_account_id' => $bankAccount->id,
            'amount' => 100,
            'date' => '2025-01-01',
            'is_paid' => false,
        ]);

        $response = $this->actingAs($user)->postJson("/api/expenses/{$expense->id}/mark-as-paid");

        $response->assertStatus(200);
        
        $this->assertTrue($expense->fresh()->is_paid);
        $this->assertEquals(900, $bankAccount->fresh()->balance);
    }

    public function test_cannot_edit_paid_expense()
    {
        $user = User::factory()->create();
        $bankAccount = BankAccount::factory()->create(['user_id' => $user->id]);
        $expense = Expense::create([
            'user_id' => $user->id,
            'bank_account_id' => $bankAccount->id,
            'amount' => 100,
            'date' => '2025-01-01',
            'is_paid' => true,
        ]);

        $response = $this->actingAs($user)->putJson("/api/expenses/{$expense->id}", [
            'amount' => 200,
            'bank_account_id' => $bankAccount->id,
            'date' => '2025-01-01',
        ]);

        $response->assertStatus(403);
    }

    public function test_cannot_delete_paid_expense()
    {
        $user = User::factory()->create();
        $bankAccount = BankAccount::factory()->create(['user_id' => $user->id]);
        $expense = Expense::create([
            'user_id' => $user->id,
            'bank_account_id' => $bankAccount->id,
            'amount' => 100,
            'date' => '2025-01-01',
            'is_paid' => true,
        ]);

        $response = $this->actingAs($user)->deleteJson("/api/expenses/{$expense->id}");

        $response->assertStatus(403);
        $this->assertDatabaseHas('expenses', ['id' => $expense->id]);
    }

    public function test_recurring_expense_creation()
    {
        $user = User::factory()->create();
        $bankAccount = BankAccount::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->postJson('/api/expenses', [
            'bank_account_id' => $bankAccount->id,
            'amount' => 100,
            'date' => '2025-01-01', // Jan 1st
            'is_paid' => false,
            'is_recurring' => true,
            'recurring_type' => 'start_of_month',
            'recur_until' => '2025-03-01', // Should create Jan, Feb, Mar
        ]);

        $response->assertStatus(201);
        
        $this->assertDatabaseCount('expenses', 3);
        // Jan 1, Feb 1, Mar 1
    }
}
