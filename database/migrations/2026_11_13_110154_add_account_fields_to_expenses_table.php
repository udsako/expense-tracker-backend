<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            if (! Schema::hasColumn('expenses', 'account_id')) {
                $table->foreignId('account_id')->nullable()->after('user_id')->constrained()->cascadeOnDelete();
            }
            if (! Schema::hasColumn('expenses', 'is_excluded')) {
                $table->boolean('is_excluded')->default(false)->after('category');
            }
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE expenses MODIFY COLUMN type ENUM('income', 'expense', 'transfer') NOT NULL");
        } elseif (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE expenses DROP CONSTRAINT IF EXISTS expenses_type_check");
            DB::statement("ALTER TABLE expenses ALTER COLUMN type TYPE VARCHAR(50)");
        }
    }

    public function down(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            if (Schema::hasColumn('expenses', 'account_id')) {
                $table->dropForeign(['account_id']);
                $table->dropColumn('account_id');
            }
            if (Schema::hasColumn('expenses', 'is_excluded')) {
                $table->dropColumn('is_excluded');
            }
        });
    }
};
