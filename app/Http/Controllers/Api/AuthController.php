<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => $validated['password'],
        ]);

        $token = $user->createToken('expense-tracker-token')->plainTextToken;

        return response()->json([
            'message' => 'Registration successful',
            'user' => $user,
            'token' => $token,
        ], 201);
    }

    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
            'remember' => 'boolean',
        ]);

        $user = User::where('email', $request->email)->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        // Remember Me: 30 days if true, 2 hours if false
        $expiresAt = $request->boolean('remember')
            ? now()->addDays(30)
            : now()->addHours(2);

        $token = $user->createToken('expense-tracker-token', ['*'], $expiresAt)->plainTextToken;

        return response()->json([
            'message' => 'Login successful',
            'user' => $user,
            'token' => $token,
        ]);
    }

    public function socialLogin(Request $request)
    {
        $validated = $request->validate([
            'id_token' => 'required|string',
            'provider' => 'required|in:google,apple',
            'name' => 'nullable|string|max:120',
        ]);

        $firebaseApiKey = env('FIREBASE_WEB_API_KEY');
        if (! $firebaseApiKey) {
            return response()->json(['message' => 'Social login is not configured on the server yet.'], 500);
        }

        $host = implode('.', ['identitytoolkit', 'googleapis', 'com']);
        $endpoint = "https://{$host}/v1/accounts:lookup";

        $verifyRes = Http::withQueryParameters([
            'key' => trim($firebaseApiKey),
        ])->post($endpoint, [
            'idToken' => $validated['id_token'],
        ]);

        if (! $verifyRes->successful() || empty($verifyRes->json('users.0'))) {
            return response()->json(['message' => 'Invalid or expired social sign-in token.'], 401);
        }

        $firebaseUser = $verifyRes->json('users.0');
        $email = strtolower(trim($firebaseUser['email'] ?? ''));

        if (! $email) {
            return response()->json(['message' => 'Could not retrieve email from social account.'], 422);
        }

        $fullName = trim($validated['name'] ?? $firebaseUser['displayName'] ?? explode('@', $email)[0]);

        $user = User::firstOrCreate(
            ['email' => $email],
            [
                'name' => $fullName,
                'password' => bcrypt(Str::random(32)),
            ]
        );

        $token = $user->createToken('expense-tracker-token')->plainTextToken;

        return response()->json([
            'message' => 'Signed in successfully!',
            'user' => $user,
            'token' => $token,
            'is_new_user' => $user->wasRecentlyCreated,
        ]);
    }

    public function updateLegalName(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|min:3|max:120',
        ]);

        $user = $request->user();
        $user->update(['name' => trim($validated['name'])]);

        return response()->json([
            'message' => 'Legal bank name saved!',
            'user' => $user,
        ]);
    }

    public function forgotPassword(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
        ]);

        $status = Password::sendResetLink($request->only('email'));

        if ($status !== Password::RESET_LINK_SENT) {
            throw ValidationException::withMessages([
                'email' => [__($status)],
            ]);
        }

        return response()->json([
            'message' => 'Password reset link sent to your email.',
        ]);
    }

    public function resetPassword(Request $request)
    {
        $request->validate([
            'token' => 'required',
            'email' => 'required|email',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password) {
                $user->forceFill([
                    'password' => $password,
                    'remember_token' => Str::random(60),
                ])->save();

                $user->tokens()->delete();

                event(new PasswordReset($user));
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages([
                'email' => [__($status)],
            ]);
        }

        return response()->json([
            'message' => 'Password has been reset successfully.',
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Logged out successfully',
        ]);
    }
}
