<!DOCTYPE html>
<html lang="ru" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Тренажёр собеседований') · {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full bg-slate-50 text-slate-900 antialiased dark:bg-slate-950 dark:text-slate-100">
<div class="min-h-full">
    <header class="border-b border-slate-200 bg-white/80 backdrop-blur dark:border-slate-800 dark:bg-slate-900/80">
        <div class="mx-auto flex max-w-6xl flex-wrap items-center gap-4 px-4 py-4">
            <a href="{{ route('dashboard') }}" class="flex items-center gap-2 text-lg font-semibold">
                <span class="grid size-9 place-items-center rounded-xl bg-indigo-600 text-white">L</span>
                <span>Тренажёр собеседований</span>
            </a>

            <nav class="ml-auto flex flex-wrap items-center gap-1 text-sm">
                @foreach ([
                    'dashboard' => 'Тренировка',
                    'questions.index' => 'Банк вопросов',
                    'progress' => 'Прогресс',
                ] as $route => $label)
                    <a href="{{ route($route) }}"
                       class="rounded-lg px-3 py-2 font-medium transition
                              {{ request()->routeIs(\Illuminate\Support\Str::before($route, '.').'*')
                                  ? 'bg-indigo-50 text-indigo-700 dark:bg-indigo-500/10 dark:text-indigo-300'
                                  : 'text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800' }}">
                        {{ $label }}
                    </a>
                @endforeach
            </nav>

            @isset($trainee)
                <div class="hidden text-right text-xs text-slate-500 sm:block dark:text-slate-400">
                    <div class="font-medium text-slate-700 dark:text-slate-200">{{ $trainee->name }}</div>
                    <div>цель: {{ $trainee->target_level->label() }}</div>
                </div>
            @endisset
        </div>
    </header>

    <main class="mx-auto max-w-6xl px-4 py-8">
        @if (session('status'))
            <div class="mb-6 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 dark:border-emerald-500/30 dark:bg-emerald-500/10 dark:text-emerald-300">
                {{ session('status') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="mb-6 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800 dark:border-rose-500/30 dark:bg-rose-500/10 dark:text-rose-300">
                <ul class="space-y-1">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @yield('content')
    </main>

    <footer class="mx-auto max-w-6xl px-4 py-10 text-xs text-slate-400">
        Тренажёр не заменяет реальный опыт — он помогает проговорить ответы вслух до собеседования.
    </footer>
</div>
</body>
</html>
