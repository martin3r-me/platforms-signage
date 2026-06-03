@props([
    'title' => null,
    'count' => null,
    'subtitle' => null,
    'icon' => null,
    'accent' => false,
])

<div class="rounded-2xl border border-[var(--ui-border)]/60 bg-[var(--ui-surface)] shadow-sm p-5 flex items-center justify-between gap-3">
    <div class="min-w-0">
        <div class="text-[11px] font-semibold uppercase tracking-wider text-[var(--ui-muted)]">{{ $title }}</div>
        <div class="mt-1 text-3xl font-bold tabular-nums {{ $accent ? 'text-[rgb(var(--ui-primary-rgb))]' : 'text-[var(--ui-secondary)]' }}">{{ $count }}</div>
        @if($subtitle)
            <div class="mt-0.5 text-xs text-[var(--ui-muted)] truncate">{{ $subtitle }}</div>
        @endif
    </div>
    @if($icon)
        <div class="w-12 h-12 shrink-0 rounded-xl bg-[var(--ui-muted-5)] flex items-center justify-center text-[var(--ui-muted)]">
            @svg('heroicon-o-'.$icon, 'w-6 h-6')
        </div>
    @endif
</div>
