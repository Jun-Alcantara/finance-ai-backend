<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('expenses', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('bank_account_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 15, 2);
            $table->text('remarks')->nullable();
            $table->date('date');
            $table->boolean('is_paid')->default(false);
            
            // Recurring fields
            $table->boolean('is_recurring')->default(false);
            $table->string('recurring_type')->nullable(); // start_of_month, end_of_month, specific_date
            $table->integer('recurring_day')->nullable();
            $table->uuid('recurring_group_id')->nullable();
            $table->date('recur_until')->nullable();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('expenses');
    }
};
