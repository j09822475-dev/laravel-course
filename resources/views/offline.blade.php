@extends('layouts.app')

@section('title', 'Нет сети')

@section('content')
    <div class="mx-auto max-w-md">
        <x-card title="Нет подключения"
                subtitle="Страница недоступна офлайн — приложению нужен сервер, чтобы записать ответ и пересчитать прогресс.">
            <p class="text-sm text-slate-600 dark:text-slate-300">
                Ответы, которые вы уже отправили, сохранены. Как только сеть вернётся, продолжите с того же вопроса.
            </p>
            <button onclick="location.reload()"
                    class="mt-4 w-full rounded-xl bg-indigo-600 px-4 py-3 text-sm font-semibold text-white hover:bg-indigo-500">
                Повторить
            </button>
        </x-card>
    </div>
@endsection
