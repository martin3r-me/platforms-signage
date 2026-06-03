@props([
    'color' => 'gray',
    'dot' => false,
])

@php
    $map = [
        'gray'    => 'bg-[var(--ui-muted-5)] text-[var(--ui-muted)]',
        'green'   => 'bg-emerald-100 text-emerald-700',
        'blue'    => 'bg-sky-100 text-sky-700',
        'violet'  => 'bg-violet-100 text-violet-700',
        'amber'   => 'bg-amber-100 text-amber-700',
        'red'     => 'bg-rose-100 text-rose-700',
        'indigo'  => 'bg-indigo-100 text-indigo-700',
    ];
    $dots = [
        'gray'    => 'bg-gray-400',
        'green'   => 'bg-emerald-500',
        'blue'    => 'bg-sky-500',
        'violet'  => 'bg-violet-500',
        'amber'   => 'bg-amber-500',
        'red'     => 'bg-rose-500',
        'indigo'  => 'bg-indigo-500',
    ];
    $cls = $map[$color] ?? $map['gray'];
    $dotCls = $dots[$color] ?? $dots['gray'];
@endphp

<span {{ $attributes->merge(['class' => 'inline-flex items-center gap-1.5 rounded-full px-2 py-0.5 text-[11px] font-medium '.$cls]) }}>
    @if($dot)<span class="h-1.5 w-1.5 rounded-full {{ $dotCls }}"></span>@endif
    {{ $slot }}
</span>
