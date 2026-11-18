<?php

namespace App\Http\Controllers;

use App\Models\Budget;
use Illuminate\Http\Request;

class BudgetController extends Controller
{
    public function index(Request $request)
    {
        $month = $request->query('month', now()->format('Y-m'));
        $accountId = $request->query('account_id');
        [$year, $monthNum] = explode('-', $month);

        $query = $request->user()->budgets()
            ->with('account:id,name,institution')
            ->where(function ($q) use ($month, $year) {
                $q->where(function ($sub) use ($month) {
                    $sub->where('period', 'monthly')->where('month', $month);
                })->orWhere(function ($sub) use ($year) {
                    $sub->where('period', 'yearly')->where('month', 'like', "{$year}-%");
                });
            });

        if ($accountId !== null && $accountId !== '') {
            $query->where('account_id', $accountId);
        }

        $budgets = $query->orderBy('id', 'desc')->get()->map(function ($budget) use ($request, $year, $monthNum) {
            $expenseQuery = $request->user()->expenses()
                ->where('type', 'expense')
                ->where('is_excluded', false)
                ->whereRaw('LOWER(category) = ?', [strtolower(trim($budget->category))])
                ->whereYear('date', $year);

            if ($budget->period !== 'yearly') {
                $expenseQuery->whereMonth('date', $monthNum);
            }

            if ($budget->account_id) {
                $expenseQuery->where('account_id', $budget->account_id);
            }

            $spent = (float) $expenseQuery->sum('amount');
            $limit = (float) $budget->amount;
            $remaining = $limit - $spent;
            $percentage = $limit > 0 ? round(($spent / $limit) * 100, 1) : 0;

            return [
                'id' => $budget->id,
                'account_id' => $budget->account_id,
                'account' => $budget->account,
                'category' => $budget->category,
                'period' => $budget->period ?? 'monthly',
                'month' => $budget->month,
                'limit' => $limit,
                'spent' => $spent,
                'remaining' => $remaining,
                'percentage' => $percentage,
                'is_exceeded' => $spent > $limit,
            ];
        });

        return response()->json(['budgets' => $budgets]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'account_id' => 'nullable|exists:accounts,id',
            'category' => 'required|string|max:100',
            'amount' => 'required|numeric|min:1',
            'period' => 'required|in:monthly,yearly',
            'month' => 'required|date_format:Y-m',
        ]);

        if (! empty($validated['account_id'])) {
            $ownsAccount = $request->user()->accounts()->where('id', $validated['account_id'])->exists();
            if (! $ownsAccount) {
                return response()->json(['message' => 'Unauthorized account selection'], 403);
            }
        }

        $categoryName = ucfirst(trim($validated['category']));
        $periodLabel = ucfirst($validated['period']);

        $budget = $request->user()->budgets()->updateOrCreate(
            [
                'account_id' => $validated['account_id'] ?? null,
                'category' => $categoryName,
                'period' => $validated['period'],
                'month' => $validated['month'],
            ],
            [
                'name' => "{$categoryName} ({$periodLabel})",
                'amount' => $validated['amount'],
            ]
        );

        return response()->json([
            'message' => "{$periodLabel} budget for {$budget->category} saved!",
            'budget' => $budget,
        ], 201);
    }

    public function destroy(Request $request, Budget $budget)
    {
        if ($budget->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $budget->delete();

        return response()->json(['message' => 'Budget deleted successfully']);
    }
}
