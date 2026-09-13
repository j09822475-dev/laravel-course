@props(['value' => 0, 'color' => 'indigo'])

@php
    $percent = max(0, min(100, (int) $value));
    $bar = [
        'indigo' => 'bg-indigo-500',
        'emerald' => 'bg-emerald-500',
        'amber' => 'bg-amber-500',
        'rose' => 'bg-rose-500',
    ][$color] ?? 'bg-indigo-500';
@endphp

<div {{ $attributes->merge(['class' => 'h-2 w-full overflow-hidden rounded-full bg-slate-200 dark:bg-slate-800']) }}>
    <div class="h-full rounded-full {{ $bar }} transition-all" style="width: {{ $percent }}%"></div>
</div>
