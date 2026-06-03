@props([
    'title' => null,
    'count' => null,
    'subtitle' => null,
    'icon' => null,
    'color' => 'indigo',
    'href' => null,
])

@php
    $chips = [
        'indigo'  => 'bg-indigo-100 text-indigo-600',
        'emerald' => 'bg-emerald-100 text-emerald-600',
        'sky'     => 'bg-sky-100 text-sky-600',
        'violet'  => 'bg-violet-100 text-violet-600',
        'amber'   => 'bg-amber-100 text-amber-600',
        'rose'    => 'bg-rose-100 text-rose-600',
    ];
    $bars = [
        'indigo'  => 'from-indigo-500/70',
        'emerald' => 'from-emerald-500/70',
        'sky'     => 'from-sky-500/70',
        'violet'  => 'from-violet-500/70',
        'amber'   => 'from-amber-500/70',
        'rose'    => 'from-rose-500/70',
    ];
    $chip = $chips[$color] ?? $chips['indigo'];
    $bar = $bars[$color] ?? $bars['indigo'];
@endphp

@php $tag = $href ? 'a' : 'div'; @endphp
<{{ $tag }} @if($href) href="{{ $href }}" wire:navigate @endif
    class="group relative block overflow-hidden rounded-2xl bg-[var(--ui-surface)] ring-1 ring-[var(--ui-border)]/60 shadow-sm transition duration-200 hover:shadow-md hover:-translate-y-0.5 {{ $href ? 'cursor-pointer' : '' }}">
    {{-- farbiger Akzentbalken oben --}}
    <div class="absolute inset-x-0 top-0 h-1 bg-gradient-to-r {{ $bar }} to-transparent"></div>

    <div class="flex items-start justify-between gap-3 p-5">
        <div class="min-w-0">
            <div class="text-[11px] font-semibold uppercase tracking-[0.12em] text-[var(--ui-muted)]">{{ $title }}</div>
            <div class="mt-2 text-4xl font-extrabold leading-none tabular-nums text-[var(--ui-secondary)]">{{ $count }}</div>
            @if($subtitle)
                <div class="mt-2 text-xs text-[var(--ui-muted)] truncate">{{ $subtitle }}</div>
            @endif
        </div>
        @if($icon)
            <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl {{ $chip }}">
                @svg('heroicon-o-'.$icon, 'w-6 h-6')
            </div>
        @endif
    </div>
</{{ $tag }}>
