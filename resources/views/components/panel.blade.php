@props([
    'title' => null,
    'subtitle' => null,
    'icon' => null,
    'color' => 'indigo',
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

<section {{ $attributes->merge(['class' => 'relative rounded-2xl bg-[var(--ui-surface)] ring-1 ring-[var(--ui-border)]/60 shadow-sm overflow-hidden']) }}>
    {{-- farbiger Akzentbalken oben (durchgängig wie bei den Dashboard-Kacheln) --}}
    <div class="absolute inset-x-0 top-0 h-1 bg-gradient-to-r {{ $bar }} to-transparent"></div>

    @if($title || $subtitle || isset($actions))
        <header class="flex items-center justify-between gap-3 px-5 py-3.5 border-b border-[var(--ui-border)]/50 bg-[var(--ui-muted-5)]/50">
            <div class="flex items-center gap-3 min-w-0">
                @if($icon)
                    <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg {{ $chip }}">
                        @svg('heroicon-o-'.$icon, 'w-4 h-4')
                    </div>
                @endif
                <div class="min-w-0">
                    @if($title)
                        <h2 class="text-sm font-semibold text-[var(--ui-secondary)] truncate">{{ $title }}</h2>
                    @endif
                    @if($subtitle)
                        <p class="text-xs text-[var(--ui-muted)] truncate">{{ $subtitle }}</p>
                    @endif
                </div>
            </div>
            @isset($actions)
                <div class="flex items-center gap-2 shrink-0">{{ $actions }}</div>
            @endisset
        </header>
    @endif

    <div>{{ $slot }}</div>
</section>
