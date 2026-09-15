@extends('layouts.app')

@section('title', 'Банк вопросов')

@section('content')
    <div class="space-y-6">
        <x-card title="Банк вопросов"
                subtitle="Все вопросы тренажёра с эталонными ответами — можно читать как справочник перед собеседованием.">
            <div class="mb-4 flex items-center gap-2 text-sm">
                <span class="text-xs text-slate-500 dark:text-slate-400">Язык:</span>
                @foreach (\App\Enums\Language::cases() as $option)
                    <a href="{{ request()->fullUrlWithQuery(['lang' => $option->value]) }}"
                       class="rounded-lg px-2.5 py-1 font-medium transition
                              {{ $language === $option
                                  ? 'bg-indigo-600 text-white'
                                  : 'border border-slate-300 text-slate-600 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800' }}">
                        {{ $option->short() }}
                    </a>
                @endforeach
            </div>

            <form method="GET" class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <input type="hidden" name="lang" value="{{ $language->value }}">
                <input name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Поиск по тексту…"
                       class="rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900">

                <select name="topic" class="rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900">
                    <option value="">Все темы</option>
                    @foreach ($topics as $topic)
                        <option value="{{ $topic->id }}" @selected(($filters['topic'] ?? null) == $topic->id)>{{ $topic->name }}</option>
                    @endforeach
                </select>

                <select name="type" class="rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900">
                    <option value="">Любой формат</option>
                    @foreach ($types as $type)
                        <option value="{{ $type->value }}" @selected(($filters['type'] ?? null) === $type->value)>{{ $type->label() }}</option>
                    @endforeach
                </select>

                <div class="flex gap-2">
                    <select name="difficulty" class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900">
                        <option value="">Любой уровень</option>
                        @foreach ($levels as $level)
                            <option value="{{ $level->value }}" @selected(($filters['difficulty'] ?? null) === $level->value)>{{ $level->label() }}</option>
                        @endforeach
                    </select>
                    <button class="rounded-xl bg-indigo-600 px-4 text-sm font-semibold text-white hover:bg-indigo-500">Найти</button>
                </div>
            </form>
        </x-card>

        <div class="space-y-3">
            @forelse ($questions as $question)
                <a href="{{ route('questions.show', [$question, 'lang' => $language->value]) }}"
                   class="block rounded-2xl border border-slate-200 bg-white p-4 transition hover:border-indigo-400 dark:border-slate-800 dark:bg-slate-900">
                    <div class="flex flex-wrap items-center gap-2">
                        <x-badge color="indigo">{{ $question->topic->name }}</x-badge>
                        <x-badge>{{ $question->type->label() }}</x-badge>
                        <x-badge color="sky">{{ $question->difficulty->label() }}</x-badge>
                    </div>
                    <p class="answer-body mt-2 text-sm font-medium">{{ \Illuminate\Support\Str::limit($question->in($language)->prompt, 220) }}</p>
                </a>
            @empty
                <x-card>
                    <p class="text-sm text-slate-500">Ничего не найдено — измените фильтры.</p>
                </x-card>
            @endforelse
        </div>

        {{ $questions->links() }}
    </div>
@endsection
