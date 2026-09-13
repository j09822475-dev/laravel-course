@extends('layouts.app')

@section('title', 'Прогресс')

@section('content')
    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            <x-card title="Владение темами"
                    subtitle="Оценка строится на вашей самооценке ответов и доле пройденных вопросов темы.">
                <ul class="space-y-4">
                    @foreach ($stats as $row)
                        <li>
                            <div class="flex flex-wrap items-center justify-between gap-2 text-sm">
                                <span class="font-medium">{{ $row['topic']->name }}</span>
                                <span class="text-xs text-slate-400">
                                    {{ $row['answered'] }} из {{ $row['topic']->questions_count }} вопросов
                                    @if ($row['failed'] > 0) · провалено {{ $row['failed'] }} @endif
                                </span>
                            </div>
                            <x-progress-bar :value="(int) round($row['mastery'] * 100)"
                                            :color="$row['mastery'] >= 0.7 ? 'emerald' : ($row['mastery'] >= 0.4 ? 'amber' : 'rose')"
                                            class="mt-1.5"/>
                        </li>
                    @endforeach
                </ul>
            </x-card>

            <x-card title="История сессий">
                @if ($sessions->isEmpty())
                    <p class="text-sm text-slate-500">Пока нет завершённых сессий.</p>
                @else
                    <table class="w-full text-sm">
                        <thead class="text-left text-xs uppercase tracking-wide text-slate-400">
                        <tr>
                            <th class="pb-2">Сессия</th>
                            <th class="pb-2">Дата</th>
                            <th class="pb-2 text-right">Результат</th>
                        </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        @foreach ($sessions as $session)
                            <tr>
                                <td class="py-2">
                                    <a href="{{ route('sessions.report', $session) }}" class="font-medium hover:text-indigo-600">
                                        {{ $session->title }}
                                    </a>
                                </td>
                                <td class="py-2 text-slate-400">{{ $session->completed_at?->translatedFormat('d M, H:i') }}</td>
                                <td class="py-2 text-right">
                                    <x-badge :color="$session->score >= 70 ? 'emerald' : ($session->score >= 40 ? 'amber' : 'rose')">
                                        {{ $session->score }}%
                                    </x-badge>
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                @endif
            </x-card>
        </div>

        <div class="space-y-6">
            <x-card title="Готовность">
                <div class="text-4xl font-semibold">{{ $readiness }}%</div>
                <x-progress-bar :value="$readiness"
                                :color="$readiness >= 70 ? 'emerald' : ($readiness >= 40 ? 'amber' : 'rose')"
                                class="mt-3"/>
                <p class="mt-3 text-xs text-slate-500 dark:text-slate-400">
                    70% и выше — можно идти на собеседование и тренировать формулировки.
                </p>
            </x-card>

            <x-card :title="'К повторению: '.$dueCount">
                @if ($due->isEmpty())
                    <p class="text-sm text-slate-500">Очередь повторений пуста.</p>
                @else
                    <ul class="space-y-3 text-sm">
                        @foreach ($due as $card)
                            <li>
                                <a href="{{ route('questions.show', $card->question) }}" class="hover:text-indigo-600">
                                    {{ \Illuminate\Support\Str::limit($card->question->prompt, 90) }}
                                </a>
                                <div class="text-xs text-slate-400">
                                    {{ $card->question->topic->name }} · интервал {{ $card->interval_days }} дн.
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-card>
        </div>
    </div>
@endsection
