@extends('layouts.app')

@section('title', 'Вопрос')

@section('content')
    <div class="mx-auto max-w-3xl space-y-6">
        <a href="{{ url()->previous() }}" class="text-sm text-slate-500 hover:text-indigo-600">← назад к списку</a>

        <x-card>
            <div class="flex flex-wrap items-center gap-2">
                <x-badge color="indigo">{{ $question->topic->name }}</x-badge>
                <x-badge>{{ $question->type->label() }}</x-badge>
                <x-badge color="sky">{{ $question->difficulty->label() }}</x-badge>
                <span class="ml-auto text-xs text-slate-400">
                    ориентир {{ (int) ceil($question->estimated_seconds / 60) }} мин
                </span>
            </div>

            <h1 class="answer-body mt-4 text-lg font-semibold leading-relaxed">{{ $question->prompt }}</h1>

            @if ($question->options)
                <ul class="mt-4 space-y-2 text-sm">
                    @foreach ($question->options as $index => $option)
                        <li class="rounded-xl border p-3 {{ $index === $question->correct_option
                            ? 'border-emerald-400 bg-emerald-50 dark:border-emerald-500/40 dark:bg-emerald-500/10'
                            : 'border-slate-200 dark:border-slate-800' }}">
                            {{ $option }}
                        </li>
                    @endforeach
                </ul>
            @else
                <div class="answer-body mt-4 text-sm leading-relaxed text-slate-700 dark:text-slate-300">{{ $question->answer }}</div>
            @endif

            @if ($question->explanation)
                <p class="mt-4 rounded-xl bg-slate-50 p-3 text-sm text-slate-600 dark:bg-slate-950 dark:text-slate-400">
                    {{ $question->explanation }}
                </p>
            @endif

            @foreach ([
                ['Что оценивает интервьюер', $question->checklist, 'text-emerald-500', '✓'],
                ['Красные флаги', $question->red_flags, 'text-rose-500', '✕'],
            ] as [$heading, $list, $color, $marker])
                @if ($list)
                    <div class="mt-5">
                        <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ $heading }}</div>
                        <ul class="mt-2 space-y-1 text-sm">
                            @foreach ($list as $point)
                                <li class="flex gap-2"><span class="{{ $color }}">{{ $marker }}</span><span>{{ $point }}</span></li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            @endforeach

            @if ($question->follow_ups)
                <div class="mt-5">
                    <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">Уточняющие вопросы интервьюера</div>
                    <ul class="mt-2 space-y-1 text-sm text-slate-600 dark:text-slate-400">
                        @foreach ($question->follow_ups as $followUp)
                            <li>— {{ $followUp }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @if ($question->tags)
                <div class="mt-5 flex flex-wrap gap-2">
                    @foreach ($question->tags as $tag)
                        <x-badge>#{{ $tag }}</x-badge>
                    @endforeach
                </div>
            @endif
        </x-card>
    </div>
@endsection
