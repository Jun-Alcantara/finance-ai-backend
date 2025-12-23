<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\SourceOfIncome;
use App\Models\BankAccount;
use App\Models\Income;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RecurringIncomeLeapYearTest extends TestCase
{
    use RefreshDatabase;

    public function test_end_of_month_recurring_income_handles_leap_year()
    {
        // Create a user
        $user = User::factory()->create();
        
        // Create a source of income
        $sourceOfIncome = SourceOfIncome::create([
            'user_id' => $user->id,
            'name' => 'Test Source',
        ]);
        
        // Create a bank account
        $bankAccount = BankAccount::create([
            'user_id' => $user->id,
            'name' => 'Test Account',
            'balance' => 1000.00,
        ]);
        
        // Create recurring income with end_of_month in a leap year (2024)
        $response = $this->actingAs($user)->postJson('/api/incomes', [
            'source_of_income_id' => $sourceOfIncome->id,
            'bank_account_id' => $bankAccount->id,
            'amount' => 5000.00,
            'remarks' => 'Monthly salary - leap year',
            'date' => '2024-01-31',
            'received' => false,
            'is_recurring' => true,
            'recurring_type' => 'end_of_month',
            'recur_until' => '2024-04-30',
        ]);
        
        $response->assertSuccessful();
        
        // Get all created incomes
        $incomes = Income::where('user_id', $user->id)
            ->orderBy('date')
            ->get();
        
        // Output for debugging
        echo "\n\n=== LEAP YEAR TEST ===\n";
        echo "Total incomes created: " . $incomes->count() . "\n\n";
        
        foreach ($incomes as $income) {
            echo "Date: " . $income->date->format('Y-m-d') . " (Day: " . $income->date->day . ")\n";
        }
        echo "\n";
        
        // We should have 4 incomes (Jan 31, Feb 29, Mar 31, Apr 30)
        $this->assertEquals(4, $incomes->count(), "Should create 4 income records");
        
        // Check each date
        $expectedDates = [
            '2024-01-31', // January - 31 days
            '2024-02-29', // February - 29 days (leap year)
            '2024-03-31', // March - 31 days
            '2024-04-30', // April - 30 days
        ];
        
        foreach ($expectedDates as $index => $expectedDate) {
            $this->assertEquals(
                $expectedDate,
                $incomes[$index]->date->format('Y-m-d'),
                "Income #{$index} should be on {$expectedDate}"
            );
        }
    }

    public function test_specific_date_recurring_income_handles_short_months()
    {
        // Create a user
        $user = User::factory()->create();
        
        // Create a source of income
        $sourceOfIncome = SourceOfIncome::create([
            'user_id' => $user->id,
            'name' => 'Test Source',
        ]);
        
        // Create a bank account
        $bankAccount = BankAccount::create([
            'user_id' => $user->id,
            'name' => 'Test Account',
            'balance' => 1000.00,
        ]);
        
        // Create recurring income on the 31st of each month
        $response = $this->actingAs($user)->postJson('/api/incomes', [
            'source_of_income_id' => $sourceOfIncome->id,
            'bank_account_id' => $bankAccount->id,
            'amount' => 5000.00,
            'remarks' => 'Specific date 31',
            'date' => '2025-01-31',
            'received' => false,
            'is_recurring' => true,
            'recurring_type' => 'specific_date',
            'recurring_day' => 31,
            'recur_until' => '2025-06-30',
        ]);
        
        $response->assertSuccessful();
        
        // Get all created incomes
        $incomes = Income::where('user_id', $user->id)
            ->orderBy('date')
            ->get();
        
        // Output for debugging
        echo "\n\n=== SPECIFIC DATE (31) TEST ===\n";
        echo "Total incomes created: " . $incomes->count() . "\n\n";
        
        foreach ($incomes as $income) {
            echo "Date: " . $income->date->format('Y-m-d') . " (Day: " . $income->date->day . ")\n";
        }
        echo "\n";
        
        // We should have 6 incomes
        $this->assertEquals(6, $incomes->count(), "Should create 6 income records");
        
        // Check each date - Feb and Apr should use last day of month
        $expectedDates = [
            '2025-01-31', // January - 31 days - uses 31
            '2025-02-28', // February - 28 days - uses 28 (last day)
            '2025-03-31', // March - 31 days - uses 31
            '2025-04-30', // April - 30 days - uses 30 (last day)
            '2025-05-31', // May - 31 days - uses 31
            '2025-06-30', // June - 30 days - uses 30 (last day)
        ];
        
        foreach ($expectedDates as $index => $expectedDate) {
            $this->assertEquals(
                $expectedDate,
                $incomes[$index]->date->format('Y-m-d'),
                "Income #{$index} should be on {$expectedDate}"
            );
        }
    }
}
