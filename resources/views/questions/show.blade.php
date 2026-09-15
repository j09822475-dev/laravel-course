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
                @if ($text->needsTranslation())
                    <x-badge color="amber">перевода нет</x-badge>
                @endif

                <span class="ml-auto flex items-center gap-2 text-xs text-slate-400">
                    <span>ориентир {{ (int) ceil($question->estimated_seconds / 60) }} мин</span>
                    @foreach (\App\Enums\Language::cases() as $option)
                        <a href="{{ route('questions.show', [$question, 'lang' => $option->value]) }}"
                           class="rounded px-1.5 py-0.5 font-semibold transition
                                  {{ $language === $option ? 'bg-indigo-600 text-white' : 'hover:text-indigo-600' }}">
                            {{ $option->short() }}
                        </a>
                    @endforeach
                </span>
            </div>

            <h1 class="answer-body mt-4 text-lg font-semibold leading-relaxed">{{ $text->prompt }}</h1>

            @if ($question->hasOptions())
                <ul class="mt-4 space-y-2 text-sm">
                    @foreach ($text->options as $index => $option)
                        <li class="rounded-xl border p-3 {{ $index === $question->correct_option
                            ? 'border-emerald-400 bg-emerald-50 dark:border-emerald-500/40 dark:bg-emerald-500/10'
                            : 'border-slate-200 dark:border-slate-800' }}">
                            {{ $option }}
                        </li>
                    @endforeach
                </ul>
            @else
                <div class="answer-body mt-4 text-sm leading-relaxed text-slate-700 dark:text-slate-300">{{ $text->answer }}</div>
            @endif

            @if ($text->explanation)
                <p class="answer-body mt-4 rounded-xl bg-slate-50 p-3 text-sm text-slate-600 dark:bg-slate-950 dark:text-slate-400">{{ $text->explanation }}</p>
            @endif

            <p class="mt-4 text-xs text-slate-500 dark:text-slate-400">{{ $question->type->technique() }}</p>

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

            @if ($text->followUps)
                <div class="mt-5">
                    <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">Уточняющие вопросы интервьюера</div>
                    <ul class="mt-2 space-y-1 text-sm text-slate-600 dark:text-slate-400">
                        @foreach ($text->followUps as $followUp)
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
