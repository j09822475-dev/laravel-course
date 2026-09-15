@extends('layouts.app')

@section('title', $session->title)

@php
    $question = $item->question;
    $language = $session->language();
    $isQuiz = $question->hasOptions();
    $isCloze = $question->type === \App\Enums\QuestionType::Cloze;
    $isShadowing = $question->type === \App\Enums\QuestionType::Shadowing;
@endphp

@section('content')
    <div class="mx-auto max-w-3xl space-y-6">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-lg font-semibold">{{ $session->title }}</h1>
                <p class="text-sm text-slate-500 dark:text-slate-400">
                    Вопрос {{ $answered + 1 }} из {{ $total }} · {{ $item->phaseLabel() }}
                </p>
            </div>
            <form method="POST" action="{{ route('sessions.destroy', $session) }}">
                @csrf
                @method('DELETE')
                <button class="text-xs font-medium text-slate-400 underline-offset-2 hover:text-rose-500 hover:underline">
                    прервать сессию
                </button>
            </form>
        </div>

        <x-progress-bar :value="(int) round($answered / max($total, 1) * 100)"/>

        <x-card>
            <div class="mb-4 flex flex-wrap items-center gap-2">
                <x-badge color="indigo">{{ $question->topic->name }}</x-badge>
                <x-badge>{{ $question->type->label() }}</x-badge>
                <x-badge color="sky">{{ $question->difficulty->label() }}</x-badge>
                @if ($language->isEnglish())
                    <x-badge color="emerald">{{ $language->short() }}</x-badge>
                @endif
                @if ($text->needsTranslation())
                    <x-badge color="amber">перевода нет — показан оригинал</x-badge>
                @endif

                <div class="ml-auto flex items-center gap-2 text-sm" data-timer data-budget="{{ $question->estimated_seconds }}">
                    <span class="text-xs text-slate-400">
                        ориентир {{ (int) ceil($question->estimated_seconds / 60) }} мин
                    </span>
                    <span class="font-mono font-semibold tabular-nums" data-timer-value>0:00</span>
                </div>
            </div>

            <div class="h-1 w-full overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800">
                <div class="h-full w-0 rounded-full bg-indigo-500 transition-all" data-timer-bar></div>
            </div>

            <p class="answer-body mt-5 text-base leading-relaxed">{{ $text->prompt }}</p>

            <p class="mt-3 text-xs text-slate-500 dark:text-slate-400">{{ $question->type->technique() }}</p>

            <form method="POST" action="{{ route('sessions.answer', $session) }}" class="mt-6 space-y-4">
                @csrf
                <input type="hidden" name="seconds" value="0" data-seconds-field>

                @if ($isQuiz)
                    <div class="space-y-2">
                        @foreach ($text->options as $index => $option)
                            <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-slate-200 p-3 text-sm transition hover:border-indigo-400 has-[:checked]:border-indigo-500 has-[:checked]:bg-indigo-50/60 dark:border-slate-800 dark:has-[:checked]:bg-indigo-500/10">
                                <input type="radio" name="option" value="{{ $index }}" required class="mt-0.5 accent-indigo-600">
                                <span>{{ $option }}</span>
                            </label>
                        @endforeach
                    </div>

                    <button class="w-full rounded-xl bg-indigo-600 px-4 py-3 text-sm font-semibold text-white hover:bg-indigo-500">
                        Ответить
                    </button>
                @elseif ($isCloze)
                    <div>
                        <label class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">
                            Впишите пропущенное слово — вспомните его сами, не подглядывая.
                        </label>
                        <input name="answer" required autocomplete="off" autofocus placeholder="слово или фраза"
                               class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-base dark:border-slate-700 dark:bg-slate-900">
                        <p class="mt-1 text-xs text-slate-400">Регистр и знаки препинания не важны.</p>
                    </div>

                    <button class="w-full rounded-xl bg-indigo-600 px-4 py-3 text-sm font-semibold text-white hover:bg-indigo-500">
                        Проверить
                    </button>
                @else
                    @if ($isShadowing)
                        <div class="rounded-xl border border-emerald-200 bg-emerald-50/60 p-4 dark:border-emerald-500/30 dark:bg-emerald-500/5">
                            <div class="text-xs font-semibold uppercase tracking-wide text-emerald-700 dark:text-emerald-300">
                                Образец для проговаривания
                            </div>
                            <div class="answer-body mt-2 text-sm leading-relaxed">{{ $text->answer }}</div>
                        </div>
                    @else
                        <div>
                            <label class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">
                                {{ $language->speakingHint() }}
                            </label>
                            <textarea name="answer" rows="5" placeholder="{{ $language->isEnglish() ? 'Key phrases you used…' : 'Ключевые тезисы вашего ответа…' }}"
                                      class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900">{{ old('answer') }}</textarea>
                        </div>

                        <details class="rounded-xl border border-slate-200 bg-slate-50 p-4 dark:border-slate-800 dark:bg-slate-900/60">
                            <summary class="cursor-pointer text-sm font-semibold text-indigo-700 dark:text-indigo-300">
                                Показать эталонный ответ
                            </summary>
                            <div class="answer-body mt-3 text-sm leading-relaxed text-slate-700 dark:text-slate-300">{{ $text->answer }}</div>

                            @if ($text->explanation)
                                <p class="answer-body mt-3 rounded-lg bg-white p-3 text-xs text-slate-600 dark:bg-slate-950 dark:text-slate-400">{{ $text->explanation }}</p>
                            @endif

                            @if ($question->checklist)
                                <div class="mt-4">
                                    <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">Что оценивает интервьюер</div>
                                    <ul class="mt-2 space-y-1 text-sm">
                                        @foreach ($question->checklist as $point)
                                            <li class="flex gap-2"><span class="text-emerald-500">✓</span><span>{{ $point }}</span></li>
                                        @endforeach
                                    </ul>
                                </div>
                            @endif

                            @if ($question->red_flags)
                                <div class="mt-4">
                                    <div class="text-xs font-semibold uppercase tracking-wide text-rose-500">Красные флаги</div>
                                    <ul class="mt-2 space-y-1 text-sm">
                                        @foreach ($question->red_flags as $point)
                                            <li class="flex gap-2"><span class="text-rose-500">✕</span><span>{{ $point }}</span></li>
                                        @endforeach
                                    </ul>
                                </div>
                            @endif

                            @if ($text->followUps)
                                <div class="mt-4">
                                    <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">Уточняющие вопросы</div>
                                    <ul class="mt-2 space-y-1 text-sm text-slate-600 dark:text-slate-400">
                                        @foreach ($text->followUps as $followUp)
                                            <li>— {{ $followUp }}</li>
                                        @endforeach
                                    </ul>
                                </div>
                            @endif
                        </details>
                    @endif

                    @if ($isShadowing && $question->explanation)
                        <p class="answer-body rounded-xl bg-slate-50 p-3 text-xs text-slate-600 dark:bg-slate-900/60 dark:text-slate-400">{{ $question->explanation }}</p>
                    @endif

                    <div>
                        <div class="mb-2 text-xs font-medium text-slate-500 dark:text-slate-400">
                            @if ($isShadowing)
                                Проговорили вслух? Оцените, насколько свободно это получилось:
                            @else
                                Оцените свой ответ честно — от этого зависит, когда вопрос вернётся:
                            @endif
                        </div>
                        <div class="grid grid-cols-2 gap-2 sm:grid-cols-4">
                            @foreach (\App\Enums\SelfRating::cases() as $rating)
                                @php
                                    $styles = [
                                        'rose' => 'border-rose-300 text-rose-700 hover:bg-rose-50 dark:border-rose-500/40 dark:text-rose-300 dark:hover:bg-rose-500/10',
                                        'amber' => 'border-amber-300 text-amber-700 hover:bg-amber-50 dark:border-amber-500/40 dark:text-amber-300 dark:hover:bg-amber-500/10',
                                        'sky' => 'border-sky-300 text-sky-700 hover:bg-sky-50 dark:border-sky-500/40 dark:text-sky-300 dark:hover:bg-sky-500/10',
                                        'emerald' => 'border-emerald-300 text-emerald-700 hover:bg-emerald-50 dark:border-emerald-500/40 dark:text-emerald-300 dark:hover:bg-emerald-500/10',
                                    ][$rating->color()];
                                @endphp
                                <button type="submit" name="rating" value="{{ $rating->value }}"
                                        class="rounded-xl border px-3 py-2.5 text-sm font-semibold transition {{ $styles }}">
                                    {{ $rating->label() }}
                                </button>
                            @endforeach
                        </div>
                    </div>
                @endif
            </form>
        </x-card>
    </div>
@endsection
