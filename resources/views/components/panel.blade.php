@props([
    'title' => null,
    'subtitle' => null,
])

<section {{ $attributes->merge(['class' => 'rounded-2xl border border-[var(--ui-border)]/60 bg-[var(--ui-surface)] shadow-sm overflow-hidden']) }}>
    @if($title || $subtitle || isset($actions))
        <header class="flex items-start justify-between gap-3 px-5 py-4 border-b border-[var(--ui-border)]/50">
            <div class="min-w-0">
                @if($title)
                    <h2 class="text-sm font-semibold text-[var(--ui-secondary)] truncate">{{ $title }}</h2>
                @endif
                @if($subtitle)
                    <p class="mt-0.5 text-xs text-[var(--ui-muted)]">{{ $subtitle }}</p>
                @endif
            </div>
            @isset($actions)
                <div class="flex items-center gap-2 shrink-0">{{ $actions }}</div>
            @endisset
        </header>
    @endif

    <div>{{ $slot }}</div>
</section>
