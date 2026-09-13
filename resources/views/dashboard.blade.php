@extends('layouts.app')

@section('title', 'Тренировка')

@section('content')
    <div class="grid gap-6 lg:grid-cols-3">
        <div class="lg:col-span-2 space-y-6">
            @if ($activeSession)
                <x-card class="border-indigo-300 bg-indigo-50/60 dark:border-indigo-500/40 dark:bg-indigo-500/5">
                    <div class="flex flex-wrap items-center justify-between gap-4">
                        <div>
                            <h2 class="text-base font-semibold">Есть незавершённая сессия</h2>
                            <p class="mt-1 text-sm text-slate-600 dark:text-slate-300">
                                {{ $activeSession->title }} · отвечено
                                {{ $activeSession->answeredCount() }} из {{ $activeSession->items()->count() }}
                            </p>
                        </div>
                        <div class="flex gap-2">
                            <a href="{{ route('sessions.show', $activeSession) }}"
                               class="rounded-xl bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500">
                                Продолжить
                            </a>
                            <form method="POST" action="{{ route('sessions.destroy', $activeSession) }}">
                                @csrf
                                @method('DELETE')
                                <button class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-medium text-slate-600 hover:bg-white dark:border-slate-700 dark:text-slate-300">
                                    Прервать
                                </button>
                            </form>
                        </div>
                    </div>
                </x-card>
            @endif

            <x-card title="Выберите формат тренировки"
                    subtitle="Сценарий собирается автоматически: вопросы подбираются под ваш целевой грейд и слабые темы.">
                <form method="POST" action="{{ route('sessions.store') }}" class="space-y-5">
                    @csrf

                    <div class="grid gap-3 sm:grid-cols-2">
                        @foreach ($modes as $mode)
                            <label class="group relative flex cursor-pointer gap-3 rounded-xl border border-slate-200 p-4 transition hover:border-indigo-400 has-[:checked]:border-indigo-500 has-[:checked]:bg-indigo-50/60 dark:border-slate-800 dark:hover:border-indigo-500 dark:has-[:checked]:bg-indigo-500/10">
                                <input type="radio" name="mode" value="{{ $mode->value }}" class="mt-1 accent-indigo-600"
                                       @checked($loop->first)>
                                <span>
                                    <span class="block text-sm font-semibold">{{ $mode->icon() }} {{ $mode->label() }}</span>
                                    <span class="mt-1 block text-xs leading-relaxed text-slate-500 dark:text-slate-400">
                                        {{ $mode->description() }}
                                    </span>
                                </span>
                            </label>
                        @endforeach
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">Уровень вопросов</label>
                            <select name="level" class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900">
                                @foreach (\App\Enums\Difficulty::cases() as $level)
                                    <option value="{{ $level->value }}" @selected($trainee->target_level === $level)>
                                        {{ $level->label() }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">Вопросов в карточках</label>
                            <input type="number" name="size" value="10" min="3" max="30"
                                   class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900">
                        </div>
                    </div>

                    <details class="rounded-xl border border-slate-200 p-4 dark:border-slate-800">
                        <summary class="cursor-pointer text-sm font-medium">Ограничить темы (необязательно)</summary>
                        <div class="mt-3 grid gap-2 sm:grid-cols-2">
                            @foreach ($topics as $topic)
                                <label class="flex items-center gap-2 text-sm">
                                    <input type="checkbox" name="topics[]" value="{{ $topic->id }}" class="accent-indigo-600">
                                    <span>{{ $topic->name }}</span>
                                    <span class="text-xs text-slate-400">{{ $topic->questions_count }}</span>
                                </label>
                            @endforeach
                        </div>
                    </details>

                    <button class="w-full rounded-xl bg-indigo-600 px-4 py-3 text-sm font-semibold text-white transition hover:bg-indigo-500">
                        Начать тренировку
                    </button>
                </form>
            </x-card>

            @if ($recent->isNotEmpty())
                <x-card title="Последние сессии">
                    <ul class="divide-y divide-slate-100 text-sm dark:divide-slate-800">
                        @foreach ($recent as $session)
                            <li class="flex items-center justify-between gap-4 py-3">
                                <div>
                                    <a href="{{ route('sessions.report', $session) }}" class="font-medium hover:text-indigo-600">
                                        {{ $session->title }}
                                    </a>
                                    <div class="text-xs text-slate-400">
                                        {{ $session->completed_at?->diffForHumans() }}
                                    </div>
                                </div>
                                <x-badge :color="$session->score >= 70 ? 'emerald' : ($session->score >= 40 ? 'amber' : 'rose')">
                                    {{ $session->score }}%
                                </x-badge>
                            </li>
                        @endforeach
                    </ul>
                </x-card>
            @endif
        </div>

        <div class="space-y-6">
            <x-card title="Готовность к собеседованию">
                <div class="flex items-end gap-3">
                    <span class="text-4xl font-semibold">{{ $readiness }}%</span>
                    <span class="pb-1 text-xs text-slate-500 dark:text-slate-400">
                        качество ответов + охват тем
                    </span>
                </div>
                <x-progress-bar :value="$readiness"
                                :color="$readiness >= 70 ? 'emerald' : ($readiness >= 40 ? 'amber' : 'rose')"
                                class="mt-3"/>

                <dl class="mt-5 grid grid-cols-2 gap-4 text-sm">
                    <div>
                        <dt class="text-xs text-slate-500 dark:text-slate-400">Сессий</dt>
                        <dd class="font-semibold">{{ $summary['sessions'] }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-slate-500 dark:text-slate-400">Ответов</dt>
                        <dd class="font-semibold">{{ $summary['answered'] }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-slate-500 dark:text-slate-400">Средний балл</dt>
                        <dd class="font-semibold">{{ $summary['average_score'] !== null ? $summary['average_score'].'%' : '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-slate-500 dark:text-slate-400">Минут тренировки</dt>
                        <dd class="font-semibold">{{ $summary['minutes'] }}</dd>
                    </div>
                </dl>
            </x-card>

            <x-card title="К повторению сегодня">
                <p class="text-sm text-slate-600 dark:text-slate-300">
                    @if ($dueCount > 0)
                        {{ \App\Support\Plural::count($dueCount, 'вопрос ждёт', 'вопроса ждут', 'вопросов ждут') }} повторения.
                        Режим карточек покажет их первыми.
                    @else
                        Ничего не ждёт повторения. Пройдите тренировку — вопросы вернутся по расписанию.
                    @endif
                </p>
            </x-card>

            @if ($weakSpots->isNotEmpty())
                <x-card title="Слабые места">
                    <ul class="space-y-3 text-sm">
                        @foreach ($weakSpots as $spot)
                            <li>
                                <div class="flex items-center justify-between gap-2">
                                    <span>{{ $spot['topic']->name }}</span>
                                    <span class="text-xs text-slate-400">{{ (int) round($spot['mastery'] * 100) }}%</span>
                                </div>
                                <x-progress-bar :value="(int) round($spot['mastery'] * 100)" color="rose" class="mt-1"/>
                            </li>
                        @endforeach
                    </ul>
                </x-card>
            @endif

            <x-card title="Профиль">
                <form method="POST" action="{{ route('profile.update') }}" class="space-y-3">
                    @csrf
                    @method('PUT')
                    <div>
                        <label class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">Как вас зовут</label>
                        <input name="name" value="{{ $trainee->name }}" maxlength="60"
                               class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900">
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">Целевой грейд</label>
                        <select name="target_level" class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900">
                            @foreach (\App\Enums\Difficulty::cases() as $level)
                                <option value="{{ $level->value }}" @selected($trainee->target_level === $level)>
                                    {{ $level->label() }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <button class="w-full rounded-xl border border-slate-300 px-4 py-2 text-sm font-medium hover:bg-slate-50 dark:border-slate-700 dark:hover:bg-slate-800">
                        Сохранить
                    </button>
                </form>
            </x-card>
        </div>
    </div>
@endsection
