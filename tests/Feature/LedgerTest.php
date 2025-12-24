<?php

namespace Tests\Feature;

use App\Models\BankAccount;
use App\Models\Expense;
use App\Models\Income;
use App\Models\SourceOfIncome;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LedgerTest extends TestCase
{
    use RefreshDatabase;

    public function test_ledger_returns_correct_data()
    {
        $user = User::factory()->create();
        $source = SourceOfIncome::factory()->create(['user_id' => $user->id]);
        $bank = BankAccount::factory()->create(['user_id' => $user->id]);

        // Create Realized Income (Credit)
        Income::create([
            'user_id' => $user->id,
            'source_of_income_id' => $source->id,
            'bank_account_id' => $bank->id,
            'amount' => 1000,
            'date' => now()->subDays(5)->format('Y-m-d'),
            'received' => true,
        ]);

        // Create Pending Income (Should be ignored)
        Income::create([
            'user_id' => $user->id,
            'source_of_income_id' => $source->id,
            'bank_account_id' => $bank->id,
            'amount' => 500,
            'date' => now()->subDays(2)->format('Y-m-d'),
            'received' => false,
        ]);

        // Create Realized Expense (Debit)
        Expense::create([
            'user_id' => $user->id,
            'bank_account_id' => $bank->id,
            'amount' => 200,
            'due_date' => now()->subDays(3)->format('Y-m-d'),
            'payment_date' => now()->subDays(3)->format('Y-m-d'),
            'is_paid' => true,
        ]);

        // Create Pending Expense (Should be ignored)
        Expense::create([
            'user_id' => $user->id,
            'bank_account_id' => $bank->id,
            'amount' => 100,
            'due_date' => now()->subDays(1)->format('Y-m-d'), // Pending
            'is_paid' => false,
        ]);

        $response = $this->actingAs($user)->getJson('/api/ledger');

        $response->assertStatus(200);

        $data = $response->json('data');
        $this->assertCount(4, $data); // Should have realized AND pending items

        // Verify Summary (Projected)
        $response->assertJsonPath('summary.total_credit', 1500); // 1000 + 500
        $response->assertJsonPath('summary.total_debit', 300);   // 200 + 100
        $response->assertJsonPath('summary.net_flow', 1200);     // 1500 - 300

        // Verify Sort Order (Ascending by date)
        // Income (-5 days) is older than Expense (-3 days).
        // So Income should be first.
        
        $this->assertEquals('credit', $data[0]['type']); // Income
        $this->assertEquals('debit', $data[1]['type']); // Expense
    }

    public function test_ledger_date_range_filtering()
    {
        $user = User::factory()->create();
        $source = SourceOfIncome::factory()->create(['user_id' => $user->id]);
        $bank = BankAccount::factory()->create(['user_id' => $user->id]);

        // Old Income (Outside range)
        Income::create([
            'user_id' => $user->id,
            'source_of_income_id' => $source->id,
            'bank_account_id' => $bank->id,
            'amount' => 1000,
            'date' => now()->subDays(40)->format('Y-m-d'),
            'received' => true,
        ]);

        // Recent Income (Inside range)
        Income::create([
            'user_id' => $user->id,
            'source_of_income_id' => $source->id,
            'bank_account_id' => $bank->id,
            'amount' => 500,
            'date' => now()->subDays(5)->format('Y-m-d'),
            'received' => true,
        ]);

        $response = $this->actingAs($user)->getJson('/api/ledger?start_date=' . now()->subDays(30)->toDateString());

        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals(500, $data[0]['amount']);
    }
    public function test_ledger_includes_future_paid_expenses_in_range()
    {
        $user = User::factory()->create();
        $bank = BankAccount::factory()->create(['user_id' => $user->id]);

        // Future Paid Expense (e.g., Dec 31, 2025)
        $futureDate = '2025-12-31';
        
        $expense = Expense::create([
            'user_id' => $user->id,
            'bank_account_id' => $bank->id,
            'amount' => 100,
            'is_paid' => true,
            'due_date' => $futureDate,
            'payment_date' => $futureDate,
        ]);

        // Range covering the date (Nov 21, 2025 to Jan 2, 2026)
        $startDate = '2025-11-21';
        $endDate = '2026-01-02';

        $response = $this->actingAs($user)->getJson("/api/ledger?start_date={$startDate}&end_date={$endDate}");

        $response->assertStatus(200);
        $data = $response->json('data');
        
        $this->assertCount(1, $data, 'Ledger should include future paid expenses within range');
        $this->assertEquals($futureDate, $data[0]['date']);
        $this->assertEquals(100, $data[0]['amount']);
    }
}
