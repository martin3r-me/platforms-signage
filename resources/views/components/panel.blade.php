@props([
    'title' => null,
    'subtitle' => null,
    'icon' => null,
])

<section {{ $attributes->merge(['class' => 'rounded-2xl bg-[var(--ui-surface)] ring-1 ring-[var(--ui-border)]/60 shadow-sm overflow-hidden']) }}>
    @if($title || $subtitle || isset($actions))
        <header class="flex items-center justify-between gap-3 px-5 py-3.5 border-b border-[var(--ui-border)]/50 bg-[var(--ui-muted-5)]/50">
            <div class="flex items-center gap-3 min-w-0">
                @if($icon)
                    <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-indigo-100 text-indigo-600">
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
