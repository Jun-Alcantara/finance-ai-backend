<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\SourceOfIncome;
use App\Models\BankAccount;
use App\Models\Income;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RecurringIncomeEndOfMonthTest extends TestCase
{
    use RefreshDatabase;

    public function test_end_of_month_recurring_income_creates_for_all_months()
    {
        // Create a user
        $user = User::factory()->create();
        
        // Create a source of income
        $source = SourceOfIncome::create([
            'user_id' => $user->id,
            'name' => 'Test Source',
        ]);
        
        // Create a bank account
        $bankAccount = BankAccount::create([
            'user_id' => $user->id,
            'name' => 'Test Account',
            'balance' => 1000.00,
        ]);
        
        // Create recurring income with end_of_month from Jan 31 to June 30
        $response = $this->actingAs($user)->postJson('/api/incomes', [
            'source_of_income_id' => $source->id,
            'bank_account_id' => $bankAccount->id,
            'amount' => 5000.00,
            'remarks' => 'Monthly salary - end of month',
            'date' => '2025-01-31',
            'received' => false,
            'is_recurring' => true,
            'recurring_type' => 'end_of_month',
            'recur_until' => '2025-06-30',
        ]);
        
        // Debug response
        if ($response->status() !== 201) {
            echo "\n\nResponse Status: " . $response->status() . "\n";
            echo "Response Body: " . json_encode($response->json(), JSON_PRETTY_PRINT) . "\n\n";
        }
        
        $response->assertSuccessful();
        
        // Get all created incomes
        $incomes = Income::where('user_id', $user->id)
            ->orderBy('date')
            ->get();
        
        // Output for debugging
        echo "\n\n=== CREATED INCOMES ===\n";
        echo "Total incomes created: " . $incomes->count() . "\n";
        echo "Recur Until: 2025-06-30\n\n";
        
        foreach ($incomes as $income) {
            echo "Date: " . $income->date->format('Y-m-d') . " (Day: " . $income->date->day . ")\n";
        }
        
        // Let's trace what the next date would be after May 31
        echo "\nNext date after May 31 would be: ";
        $testDate = \Carbon\Carbon::parse('2025-05-31');
        $nextDate = $testDate->copy()->addMonthNoOverflow()->endOfMonth();
        echo $nextDate->format('Y-m-d') . "\n";
        echo "Is 2025-06-30 <= 2025-06-30? " . ($nextDate->lte(\Carbon\Carbon::parse('2025-06-30')) ? 'YES' : 'NO') . "\n";
        echo "\n";
        
        // We should have 6 incomes (Jan 31, Feb 28, Mar 31, Apr 30, May 31, Jun 30)
        $this->assertEquals(6, $incomes->count(), "Should create 6 income records");
        
        // Check each date
        $expectedDates = [
            '2025-01-31', // January - 31 days
            '2025-02-28', // February - 28 days (2025 is not a leap year)
            '2025-03-31', // March - 31 days
            '2025-04-30', // April - 30 days
            '2025-05-31', // May - 31 days
            '2025-06-30', // June - 30 days
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
