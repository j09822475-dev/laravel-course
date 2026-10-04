@extends('layouts.app')

@section('title', 'Регистрация')

@section('content')
    <div class="mx-auto max-w-md">
        <x-card title="Создать аккаунт"
                subtitle="Прогресс перестанет зависеть от браузера: его можно открыть с телефона и показать по ссылке.">
            <form method="POST" action="{{ route('register') }}" class="space-y-4">
                @csrf

                <div>
                    <label for="name" class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">Имя</label>
                    <input id="name" name="name" value="{{ old('name') }}" required autofocus autocomplete="name" maxlength="60"
                           class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-base dark:border-slate-700 dark:bg-slate-900">
                </div>

                <div>
                    <label for="email" class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">Почта</label>
                    <input id="email" name="email" type="email" inputmode="email" value="{{ old('email') }}" required autocomplete="email"
                           class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-base dark:border-slate-700 dark:bg-slate-900">
                </div>

                <div>
                    <label for="password" class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">Пароль</label>
                    <input id="password" name="password" type="password" required autocomplete="new-password"
                           class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-base dark:border-slate-700 dark:bg-slate-900">
                    <p class="mt-1 text-xs text-slate-400">Минимум 8 символов.</p>
                </div>

                <div>
                    <label for="password_confirmation" class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">Пароль ещё раз</label>
                    <input id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password"
                           class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-base dark:border-slate-700 dark:bg-slate-900">
                </div>

                <button class="w-full rounded-xl bg-indigo-600 px-4 py-3 text-sm font-semibold text-white transition hover:bg-indigo-500">
                    Создать аккаунт
                </button>

                <p class="text-center text-sm text-slate-500 dark:text-slate-400">
                    Уже есть аккаунт? <a href="{{ route('login') }}" class="font-medium text-indigo-600 hover:underline">Войти</a>
                </p>
            </form>
        </x-card>
    </div>
@endsection
