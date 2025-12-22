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
        Schema::create('incomes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('category_id')->constrained()->onDelete('restrict');
            $table->foreignUuid('bank_account_id')->constrained()->onDelete('restrict');
            $table->decimal('amount', 15, 2);
            $table->text('remarks')->nullable();
            $table->date('date');
            $table->boolean('received')->default(false);
            
            // Recurring fields
            $table->boolean('is_recurring')->default(false);
            $table->enum('recurring_type', ['specific_date', 'start_of_month', 'end_of_month'])->nullable();
            $table->integer('recurring_day')->nullable(); // Day of month (1-31) for specific_date type
            $table->uuid('recurring_group_id')->nullable(); // To link recurring incomes together
            
            $table->timestamps();
            $table->softDeletes();
            
            $table->index(['user_id', 'date']);
            $table->index(['recurring_group_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('incomes');
    }
};
