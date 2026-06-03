<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="Bildschirme" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'Digital Signage', 'href' => route('signage.dashboard'), 'icon' => 'tv'],
            ['label' => 'Bildschirme'],
        ]">
            <x-ui-button wire:click="openCreateModal" variant="primary" size="sm">
                @svg('heroicon-o-link', 'w-4 h-4')
                Bildschirm koppeln
            </x-ui-button>
        </x-ui-page-actionbar>
    </x-slot>

    <x-ui-page-container>
        <div class="space-y-6 pt-4">
            @if(session('signage_message'))
                <div class="p-3 rounded bg-green-100 text-green-800 text-sm">{{ session('signage_message') }}</div>
            @endif

            <x-signage-panel icon="computer-desktop" title="Gekoppelte Bildschirme">
                <div class="divide-y divide-[var(--ui-border)]/40">
                    @forelse($screens as $screen)
                        <div class="flex items-center justify-between p-4" wire:key="screen-{{ $screen->id }}">
                            <a href="{{ route('signage.screens.show', $screen) }}" wire:navigate class="flex items-center gap-3 flex-1 min-w-0">
                                <x-signage-badge :color="$screen->isOnline() ? 'green' : 'gray'" dot class="shrink-0">
                                    {{ $screen->isOnline() ? 'Online' : 'Offline' }}
                                </x-signage-badge>
                                <div class="min-w-0">
                                    <div class="font-medium text-[var(--ui-secondary)] truncate">{{ $screen->name }}</div>
                                    <div class="text-xs text-[var(--ui-muted)] truncate">
                                        {{ $screen->isOnline() ? 'Gerade erreichbar' : ($screen->last_seen_at ? 'Zuletzt '.$screen->last_seen_at->diffForHumans() : 'Noch nie online') }}
                                        @if($screen->defaultPlaylist) · {{ $screen->defaultPlaylist->name }} @endif
                                    </div>
                                </div>
                            </a>
                            <div class="flex items-center gap-2">
                                <x-ui-button size="sm" variant="secondary" wire:click="reload({{ $screen->id }})" title="Neuladen erzwingen">
                                    @svg('heroicon-o-arrow-path', 'w-4 h-4')
                                </x-ui-button>
                                <button wire:click="deleteScreen({{ $screen->id }})"
                                        wire:confirm="Bildschirm „{{ $screen->name }}“ entfernen?"
                                        class="p-1.5 rounded text-[var(--ui-muted)] hover:text-red-600">
                                    @svg('heroicon-o-trash', 'w-4 h-4')
                                </button>
                            </div>
                        </div>
                    @empty
                        <div class="p-6 text-center text-[var(--ui-muted)]">Noch keine Bildschirme gekoppelt.</div>
                    @endforelse
                </div>
            </x-signage-panel>
        </div>

        {{-- Dialog: Bildschirm koppeln --}}
        @if($showCreateModal)
            <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
                 wire:click.self="closeCreateModal" wire:key="screen-create-modal">
                <div class="bg-[var(--ui-bg,#fff)] rounded-xl shadow-2xl w-full max-w-lg">
                    <div class="flex items-center justify-between px-5 py-3 border-b border-[var(--ui-border)]/40">
                        <h3 class="font-semibold text-[var(--ui-secondary)]">Bildschirm koppeln</h3>
                        <button wire:click="closeCreateModal" class="p-1 rounded text-[var(--ui-muted)] hover:text-[var(--ui-secondary)]">
                            @svg('heroicon-o-x-mark', 'w-5 h-5')
                        </button>
                    </div>
                    <form wire:submit="pair" class="space-y-4 p-5">
                        <p class="text-xs text-[var(--ui-muted)]">Öffne <span class="font-medium">{{ url('/signage/play') }}</span> am TV und gib den dort angezeigten Code ein.</p>
                        <div>
                            <x-ui-input-text name="pairingCode" label="Kopplungs-Code" wire:model="pairingCode" placeholder="z.B. K7P2QM" />
                            @error('pairingCode')<span class="text-xs text-red-600">{{ $message }}</span>@enderror
                        </div>
                        <x-ui-input-text name="pairingName" label="Name (optional)" wire:model="pairingName" placeholder="z.B. Empfang Foyer" />
                        <div class="flex items-center justify-end gap-2 pt-1">
                            <x-ui-button type="button" variant="secondary" wire:click="closeCreateModal">Abbrechen</x-ui-button>
                            <x-ui-button type="submit" variant="primary">Koppeln</x-ui-button>
                        </div>
                    </form>
                </div>
            </div>
        @endif
    </x-ui-page-container>
</x-ui-page>
