<div class="divide-y divide-[var(--ui-border)]/40">
    @forelse($items as $playlist)
        <div class="flex items-center justify-between p-4" wire:key="pl-{{ $playlist->id }}">
            <a href="{{ route('signage.playlists.show', $playlist) }}" wire:navigate class="flex-1">
                <div class="font-medium text-[var(--ui-secondary)]">{{ $playlist->name }}</div>
                <div class="text-xs text-[var(--ui-muted)]">{{ $playlist->items_count }} Elemente</div>
            </a>
            <button wire:click="deletePlaylist({{ $playlist->id }})"
                    wire:confirm="Wiedergabeliste „{{ $playlist->name }}“ löschen?"
                    class="p-1.5 rounded text-[var(--ui-muted)] hover:text-red-600">
                @svg('heroicon-o-trash', 'w-4 h-4')
            </button>
        </div>
    @empty
        <div class="p-6 text-center text-[var(--ui-muted)] text-sm">Keine Listen vorhanden.</div>
    @endforelse
</div>
