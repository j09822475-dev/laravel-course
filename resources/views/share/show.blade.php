@extends('layouts.app')

@section('title', 'Прогресс · '.$owner->name)

@section('content')
    <div class="mx-auto max-w-3xl space-y-6">
        <x-card>
            <div class="flex flex-wrap items-start justify-between gap-6">
                <div>
                    <p class="text-xs uppercase tracking-wide text-slate-400">Прогресс подготовки</p>
                    <h1 class="mt-1 text-xl font-semibold">{{ $owner->name }}</h1>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                        Цель: {{ $owner->target_level->label() }} · подготовка к собеседованию на full stack Laravel developer
                    </p>
                </div>
                <div class="text-right">
                    <div class="text-4xl font-semibold">{{ $readiness }}%</div>
                    <x-badge :color="$readiness >= 70 ? 'emerald' : ($readiness >= 40 ? 'amber' : 'rose')">готовность</x-badge>
                </div>
            </div>
            <x-progress-bar :value="$readiness"
                            :color="$readiness >= 70 ? 'emerald' : ($readiness >= 40 ? 'amber' : 'rose')"
                            class="mt-4"/>

            <dl class="mt-5 grid grid-cols-2 gap-4 text-sm sm:grid-cols-4">
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

        @if ($stats->isNotEmpty())
            <x-card title="Владение темами">
                <ul class="space-y-4">
                    @foreach ($stats as $row)
                        <li>
                            <div class="flex flex-wrap items-center justify-between gap-2 text-sm">
                                <span class="font-medium">{{ $row['topic']->name }}</span>
                                <span class="text-xs text-slate-400">{{ $row['answered'] }} из {{ $row['topic']->questions_count }} вопросов</span>
                            </div>
                            <x-progress-bar :value="(int) round($row['mastery'] * 100)"
                                            :color="$row['mastery'] >= 0.7 ? 'emerald' : ($row['mastery'] >= 0.4 ? 'amber' : 'rose')"
                                            class="mt-1.5"/>
                        </li>
                    @endforeach
                </ul>
            </x-card>
        @endif

        @if ($sessions->isNotEmpty())
            <x-card title="Последние тренировки">
                <ul class="divide-y divide-slate-100 text-sm dark:divide-slate-800">
                    @foreach ($sessions as $session)
                        <li class="flex items-center justify-between gap-4 py-3">
                            <div>
                                <div class="font-medium">{{ $session->title }}</div>
                                <div class="text-xs text-slate-400">{{ $session->completed_at?->translatedFormat('d F, H:i') }}</div>
                            </div>
                            <x-badge :color="$session->score >= 70 ? 'emerald' : ($session->score >= 40 ? 'amber' : 'rose')">
                                {{ $session->score }}%
                            </x-badge>
                        </li>
                    @endforeach
                </ul>
            </x-card>
        @endif

        <x-card>
            <p class="text-sm text-slate-600 dark:text-slate-300">
                Это страница только для чтения: ответы кандидата и личные данные на ней не показываются.
                <a href="{{ route('dashboard') }}" class="font-medium text-indigo-600 hover:underline">Открыть тренажёр</a>
                и начать свою подготовку.
            </p>
        </x-card>
    </div>
@endsection
