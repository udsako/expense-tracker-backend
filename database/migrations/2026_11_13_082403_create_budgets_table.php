<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('budgets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name'); // e.g., "Monthly Living Budget" or "Weekly Food"
            $table->decimal('amount', 10, 2);
            $table->enum('period', ['weekly', 'bi-weekly', 'monthly', 'yearly']);
            $table->string('category')->nullable(); // null means applies to all expenses
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('budgets');
    }
};
