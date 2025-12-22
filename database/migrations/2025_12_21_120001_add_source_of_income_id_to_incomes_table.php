<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Truncate first to avoid constraint errors
        DB::table('incomes')->delete();
        
        if (Schema::hasColumn('incomes', 'category_id')) {
            Schema::table('incomes', function (Blueprint $table) {
                // Check if foreign key exists if possible, but usually safe to assume if column exists
                try {
                    $table->dropForeign(['category_id']);
                } catch (\Exception $e) {
                    // Ignore if FK doesn't exist
                }
                $table->dropColumn('category_id');
            });
        }
        
        if (!Schema::hasColumn('incomes', 'source_of_income_id')) {
            Schema::table('incomes', function (Blueprint $table) {
                $table->foreignUuid('source_of_income_id')->constrained('source_of_incomes')->onDelete('restrict');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('incomes')->delete();

        if (Schema::hasColumn('incomes', 'source_of_income_id')) {
             Schema::table('incomes', function (Blueprint $table) {
                $table->dropForeign(['source_of_income_id']);
                $table->dropColumn('source_of_income_id');
             });
        }

        if (!Schema::hasColumn('incomes', 'category_id')) {
            Schema::table('incomes', function (Blueprint $table) {
                $table->foreignId('category_id')->nullable()->constrained()->onDelete('restrict');
            });
        }
    }
};
