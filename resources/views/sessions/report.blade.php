@extends('layouts.app')

@section('title', 'Отчёт по сессии')

@php
    $items = $session->items;
    $byPhase = $items->groupBy('phase');
    $scoreColor = $session->score >= 70 ? 'emerald' : ($session->score >= 40 ? 'amber' : 'rose');
@endphp

@section('content')
    <div class="mx-auto max-w-4xl space-y-6">
        <x-card>
            <div class="flex flex-wrap items-start justify-between gap-6">
                <div>
                    <h1 class="text-xl font-semibold">{{ $session->title }}</h1>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                        {{ \App\Support\Plural::count($items->count(), 'вопрос', 'вопроса', 'вопросов') }} ·
                        {{ (int) round($session->total_seconds / 60) }} мин ·
                        {{ $session->completed_at?->translatedFormat('d F, H:i') }}
                    </p>
                </div>
                <div class="text-right">
                    <div class="text-4xl font-semibold">{{ $session->score }}%</div>
                    <x-badge :color="$scoreColor">
                        @if ($session->score >= 70) Готовы к собеседованию
                        @elseif ($session->score >= 40) Нужна доработка
                        @else Есть пробелы в базе
                        @endif
                    </x-badge>
                </div>
            </div>
            <x-progress-bar :value="$session->score" :color="$scoreColor" class="mt-4"/>
        </x-card>

        @if ($advice)
            <x-card title="Что делать дальше">
                <ul class="space-y-2 text-sm">
                    @foreach ($advice as $line)
                        <li class="flex gap-2"><span class="text-indigo-500">→</span><span>{{ $line }}</span></li>
                    @endforeach
                </ul>
            </x-card>
        @endif

        @foreach ($byPhase as $phase => $phaseItems)
            <x-card :title="$phaseItems->first()->phaseLabel()">
                <ul class="space-y-4">
                    @foreach ($phaseItems as $item)
                        @php $rating = $item->rating(); @endphp
                        <li class="rounded-xl border border-slate-200 p-4 dark:border-slate-800">
                            <div class="flex flex-wrap items-center gap-2">
                                <x-badge color="indigo">{{ $item->question->topic->name }}</x-badge>
                                @if ($rating)
                                    <x-badge :color="$rating->color()">{{ $rating->label() }}</x-badge>
                                @endif
                                @if ($item->is_correct !== null)
                                    <x-badge :color="$item->is_correct ? 'emerald' : 'rose'">
                                        {{ $item->is_correct ? 'верно' : 'неверно' }}
                                    </x-badge>
                                @endif
                                <span class="ml-auto text-xs text-slate-400">
                                    {{ $item->seconds_spent }} с из {{ $item->question->estimated_seconds }} с
                                </span>
                            </div>

                            <p class="answer-body mt-3 text-sm font-medium">{{ $item->question->prompt }}</p>

                            @if ($item->answer_text)
                                <div class="mt-3 rounded-lg bg-slate-50 p-3 text-sm dark:bg-slate-950">
                                    <div class="mb-1 text-xs font-semibold text-slate-500">Ваш ответ</div>
                                    <div class="answer-body">{{ $item->answer_text }}</div>
                                </div>
                            @endif

                            <details class="mt-3">
                                <summary class="cursor-pointer text-sm font-medium text-indigo-700 dark:text-indigo-300">
                                    Эталонный ответ
                                </summary>
                                <div class="answer-body mt-2 text-sm leading-relaxed text-slate-700 dark:text-slate-300">{{ $item->question->answer }}</div>
                                @if ($item->question->explanation)
                                    <p class="mt-2 text-xs text-slate-500 dark:text-slate-400">{{ $item->question->explanation }}</p>
                                @endif
                            </details>
                        </li>
                    @endforeach
                </ul>
            </x-card>
        @endforeach

        <div class="flex flex-wrap gap-3">
            <a href="{{ route('dashboard') }}"
               class="rounded-xl bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-indigo-500">
                Новая тренировка
            </a>
            <a href="{{ route('progress') }}"
               class="rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-medium hover:bg-white dark:border-slate-700 dark:hover:bg-slate-800">
                Посмотреть прогресс
            </a>
        </div>
    </div>
@endsection
