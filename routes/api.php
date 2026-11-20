<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\BudgetController;
use App\Http\Controllers\ExpenseController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Public Auth Routes
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/auth/social', [AuthController::class, 'socialLogin']);
Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
Route::post('/reset-password', [AuthController::class, 'resetPassword']);

// Protected Routes (Requires valid Sanctum Token)
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    Route::put('/user/legal-name', [AuthController::class, 'updateLegalName']);
    Route::post('/logout', [AuthController::class, 'logout']);

    // Accounts, Statement Preview & Import, Reconciliation
    Route::apiResource('accounts', AccountController::class)->only(['index', 'store', 'show', 'destroy']);
    Route::post('/accounts/{account}/preview-statement', [AccountController::class, 'previewStatement']);
    Route::post('/accounts/{account}/confirm-import', [AccountController::class, 'confirmImport']);
    Route::post('/accounts/{account}/reconcile', [AccountController::class, 'reconcile']);

    // Expense Endpoints (Clarification & Delete only — no manual creation)
    Route::apiResource('expenses', ExpenseController::class)->only(['index', 'update', 'destroy']);

    // Budget Endpoints
    Route::apiResource('budgets', BudgetController::class)->only(['index', 'store', 'destroy']);

    // Annual / End-of-Year Analytics Summary
    Route::get('/annual-summary', [ExpenseController::class, 'annualSummary']);
});
