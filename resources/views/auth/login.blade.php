@extends('layouts.app')

@section('title', 'Вход')

@section('content')
    <div class="mx-auto max-w-md">
        <x-card title="Вход" subtitle="Прогресс подтянется из аккаунта — на любом устройстве он будет одинаковым.">
            <form method="POST" action="{{ route('login') }}" class="space-y-4">
                @csrf

                <div>
                    <label for="email" class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">Почта</label>
                    <input id="email" name="email" type="email" inputmode="email" value="{{ old('email') }}" required autofocus autocomplete="email"
                           class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-base dark:border-slate-700 dark:bg-slate-900">
                </div>

                <div>
                    <label for="password" class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">Пароль</label>
                    <input id="password" name="password" type="password" required autocomplete="current-password"
                           class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-base dark:border-slate-700 dark:bg-slate-900">
                </div>

                <label class="flex items-center gap-2 text-sm text-slate-600 dark:text-slate-300">
                    <input type="checkbox" name="remember" value="1" class="accent-indigo-600">
                    Запомнить меня
                </label>

                <button class="w-full rounded-xl bg-indigo-600 px-4 py-3 text-sm font-semibold text-white transition hover:bg-indigo-500">
                    Войти
                </button>

                <p class="text-center text-sm text-slate-500 dark:text-slate-400">
                    Нет аккаунта? <a href="{{ route('register') }}" class="font-medium text-indigo-600 hover:underline">Зарегистрироваться</a>
                </p>
            </form>
        </x-card>
    </div>
@endsection
