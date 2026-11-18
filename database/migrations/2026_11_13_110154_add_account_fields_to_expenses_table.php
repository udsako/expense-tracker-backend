<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Expand 'type' enum to include 'transfer'
        DB::statement("ALTER TABLE expenses MODIFY COLUMN type ENUM('income', 'expense', 'transfer') NOT NULL");

        Schema::table('expenses', function (Blueprint $table) {
            $table->foreignId('account_id')->nullable()->after('user_id')->constrained()->cascadeOnDelete();
            $table->boolean('is_excluded')->default(false)->after('date');
        });
    }

    public function down(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->dropForeign(['account_id']);
            $table->dropColumn(['account_id', 'is_excluded']);
        });
    }
};
