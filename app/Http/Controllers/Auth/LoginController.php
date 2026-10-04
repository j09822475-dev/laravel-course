<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\ProgressSync;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class LoginController extends Controller
{
    public function create(): View
    {
        return view('auth.login');
    }

    public function store(Request $request, ProgressSync $sync): RedirectResponse
    {
        $data = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
            'remember' => ['nullable', 'boolean'],
        ]);

        $this->ensureIsNotRateLimited($request);

        if (! Auth::attempt(['email' => $data['email'], 'password' => $data['password']], $request->boolean('remember'))) {
            RateLimiter::hit($this->throttleKey($request));

            // Не подсказываем, существует ли адрес: сообщение одно на оба случая.
            throw ValidationException::withMessages([
                'email' => 'Неверный адрес или пароль.',
            ]);
        }

        RateLimiter::clear($this->throttleKey($request));

        $guest = $request->attributes->get('trainee');

        $request->session()->regenerate();

        $sync->attach($request->user(), $guest);

        return redirect()->intended(route('dashboard'))->with('status', 'С возвращением! Прогресс загружен из аккаунта.');
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('dashboard')->with('status', 'Вы вышли из аккаунта.');
    }

    /** Подбор пароля ограничен: пять попыток на пару адрес + IP. */
    protected function ensureIsNotRateLimited(Request $request): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey($request), maxAttempts: 5)) {
            return;
        }

        $seconds = RateLimiter::availableIn($this->throttleKey($request));

        throw ValidationException::withMessages([
            'email' => "Слишком много попыток входа. Попробуйте через {$seconds} с.",
        ]);
    }

    protected function throttleKey(Request $request): string
    {
        return 'login:'.mb_strtolower((string) $request->input('email')).'|'.$request->ip();
    }
}
