<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\Expense;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ExpenseController extends Controller
{
    public function index(Request $request)
    {
        $month = $request->query('month', now()->format('Y-m'));
        [$year, $monthNum] = explode('-', $month);

        $expenses = $request->user()
            ->expenses()
            ->with('account:id,name,institution,type')
            ->whereYear('date', $year)
            ->whereMonth('date', $monthNum)
            ->orderBy('date', 'desc')
            ->orderBy('id', 'desc')
            ->get();

        // Count all real income and expenses (only inter-account transfers are excluded from totals)
        $monthlyIncome = $expenses->where('type', 'income')->sum('amount');
        $monthlyExpense = $expenses->where('type', 'expense')->sum('amount');

        $overallBalance = $request->user()->accounts()->sum('balance');

        // Count all transactions across the user's history that still need clarification (is_excluded = true)
        $unclarifiedQuery = $request->user()->expenses()->where('is_excluded', true);
        $unclarifiedCount = $unclarifiedQuery->count();
        $unclarifiedTotal = $unclarifiedQuery->sum('amount');

        return response()->json([
            'expenses' => $expenses,
            'summary' => [
                'total_income' => (float) $monthlyIncome,
                'total_expense' => (float) $monthlyExpense,
                'balance' => (float) ($monthlyIncome - $monthlyExpense),
                'overall_balance' => (float) $overallBalance,
                'unclarified_count' => $unclarifiedCount,
                'unclarified_total' => (float) $unclarifiedTotal,
            ],
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'account_id' => 'required|exists:accounts,id',
            'title' => 'required|string|max:255',
            'amount' => 'required|numeric|min:0.01',
            'type' => 'required|in:income,expense',
            'category' => 'required|string|max:100',
            'date' => 'required|date|before_or_equal:today',
            'is_excluded' => 'boolean',
        ]);

        $account = Account::findOrFail($validated['account_id']);

        if ($account->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized account selection.'], 403);
        }

        // Per-Account Overdraft Protection
        if ($validated['type'] === 'expense' && $validated['amount'] > $account->balance) {
            return response()->json([
                'message' => "Insufficient balance in {$account->name}. Available balance is ₦" . number_format($account->balance, 2) . ".",
            ], 422);
        }

        $needsClarification = $request->boolean('is_excluded');

        $finalCategory = 'Uncategorized';
        if (! $needsClarification) {
            $inferred = AccountController::classifyNarration($validated['title'])
                ?? AccountController::classifyNarration($validated['category']);
            $finalCategory = $inferred ?? ucfirst(trim($validated['category']));
        }

        $expense = DB::transaction(function () use ($request, $account, $validated, $needsClarification, $finalCategory) {
            $expense = $request->user()->expenses()->create([
                'account_id' => $account->id,
                'title' => $validated['title'],
                'amount' => $validated['amount'],
                'type' => $validated['type'],
                'category' => $finalCategory,
                'date' => $validated['date'],
                'is_excluded' => $needsClarification,
            ]);

            if ($validated['type'] === 'income') {
                $account->increment('balance', $validated['amount']);
            } else {
                $account->decrement('balance', $validated['amount']);
            }

            return $expense->load('account:id,name,institution,type');
        });

        return response()->json([
            'message' => 'Transaction added successfully',
            'expense' => $expense,
        ], 201);
    }

    public function update(Request $request, Expense $expense)
    {
        if ($expense->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Only allow clarifying the description/category — real statement amounts & types stay locked!
        $validated = $request->validate([
            'title' => 'sometimes|string|max:255',
            'category' => 'sometimes|string|max:100',
            'is_excluded' => 'sometimes|boolean',
        ]);

        if (isset($validated['title'])) {
            $inferred = AccountController::classifyNarration($validated['title']);
            if ($inferred && (! isset($validated['category']) || strtolower($validated['category']) === 'uncategorized' || $validated['category'] === '')) {
                $validated['category'] = $inferred;
            }
        }
        if (isset($validated['category'])) {
            $inferredFromCat = AccountController::classifyNarration($validated['category']);
            $validated['category'] = $inferredFromCat ?? ucfirst(trim($validated['category']));
        }

        $expense->update($validated);

        return response()->json([
            'message' => 'Transaction clarified successfully',
            'expense' => $expense->fresh('account:id,name,institution,type'),
        ]);
    }


    public function destroy(Request $request, Expense $expense)
    {
        if ($expense->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $account = $expense->account;

        if ($account && $expense->type === 'income' && ($account->balance - $expense->amount) < 0) {
            return response()->json([
                'message' => "Cannot delete this income because {$account->name}'s balance would drop below ₦0.",
            ], 422);
        }

        DB::transaction(function () use ($expense, $account) {
            if ($account) {
                if ($expense->type === 'income') {
                    $account->decrement('balance', $expense->amount);
                } elseif ($expense->type === 'expense') {
                    $account->increment('balance', $expense->amount);
                }
            }

            $expense->delete();
        });

        return response()->json([
            'message' => 'Transaction deleted successfully',
        ]);
    }

        public function annualSummary(Request $request)
    {
        $year = (string) $request->query('year', now()->format('Y'));
        $accountId = $request->query('account_id');

        $query = $request->user()->expenses()
            ->whereYear('date', $year);

        if ($accountId !== null && $accountId !== '') {
            $query->where('account_id', $accountId);
        }

        $transactions = $query->get();

        $totalIncome = (float) $transactions->where('type', 'income')->sum('amount');
        $totalExpense = (float) $transactions->where('type', 'expense')->sum('amount');

        // 1. Month-by-Month Trend (Jan - Dec)
        $monthNames = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        $monthlyTrend = [];
        for ($m = 1; $m <= 12; $m++) {
            $monthPrefix = sprintf('%s-%02d', $year, $m);
            $monthItems = $transactions->filter(fn ($tx) => str_starts_with((string) $tx->date, $monthPrefix));

            $monthlyTrend[] = [
                'month' => $monthNames[$m - 1],
                'Income' => (float) $monthItems->where('type', 'income')->sum('amount'),
                'Expenses' => (float) $monthItems->where('type', 'expense')->sum('amount'),
            ];
        }

        // 2. Full-Year Category Breakdown
        $categoryBreakdown = $transactions
            ->where('type', 'expense')
            ->groupBy(function ($tx) {
                if ($tx->is_excluded || strtolower((string) $tx->category) === 'uncategorized') {
                    return 'Undocumented';
                }
                return ucfirst(trim((string) $tx->category));
            })
            ->map(fn ($items, $category) => [
                'category' => $category,
                'total' => (float) $items->sum('amount'),
                'count' => $items->count(),
            ])
            ->sortByDesc('total')
            ->values();

        return response()->json([
            'year' => $year,
            'summary' => [
                'total_income' => $totalIncome,
                'total_expense' => $totalExpense,
                'net_savings' => $totalIncome - $totalExpense,
                'top_category' => $categoryBreakdown->first()['category'] ?? 'None',
                'top_category_amount' => $categoryBreakdown->first()['total'] ?? 0,
            ],
            'monthly_trend' => $monthlyTrend,
            'category_breakdown' => $categoryBreakdown,
        ]);
    }

}
