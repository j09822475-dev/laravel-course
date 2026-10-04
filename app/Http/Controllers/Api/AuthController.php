<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\ProgressSync;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

/**
 * Аутентификация мобильного клиента по токенам Sanctum.
 * Токен привязан к устройству, поэтому его можно отозвать по отдельности.
 */
class AuthController extends Controller
{
    public function register(Request $request, ProgressSync $sync): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:60'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::defaults()],
            'device' => ['nullable', 'string', 'max:60'],
        ]);

        $user = User::create($data);
        $sync->attach($user);

        return response()->json([
            'token' => $user->createToken($data['device'] ?? 'mobile')->plainTextToken,
            'user' => ['name' => $user->name, 'email' => $user->email],
        ], 201);
    }

    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
            'device' => ['nullable', 'string', 'max:60'],
        ]);

        $key = 'api-login:'.mb_strtolower($data['email']).'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($key, maxAttempts: 5)) {
            throw ValidationException::withMessages([
                'email' => 'Слишком много попыток входа. Попробуйте через '.RateLimiter::availableIn($key).' с.',
            ]);
        }

        $user = User::firstWhere('email', $data['email']);

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            RateLimiter::hit($key);

            throw ValidationException::withMessages(['email' => 'Неверный адрес или пароль.']);
        }

        RateLimiter::clear($key);

        return response()->json([
            'token' => $user->createToken($data['device'] ?? 'mobile')->plainTextToken,
            'user' => ['name' => $user->name, 'email' => $user->email],
        ]);
    }

    /** Выход с текущего устройства: остальные токены продолжают работать. */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Токен отозван.']);
    }
}
