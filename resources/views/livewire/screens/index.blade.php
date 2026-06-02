<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="Bildschirme" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'Digital Signage', 'href' => route('signage.dashboard'), 'icon' => 'tv'],
            ['label' => 'Bildschirme'],
        ]" />
    </x-slot>

    <x-ui-page-container>
        <div class="space-y-6">
            @if(session('signage_message'))
                <div class="p-3 rounded bg-green-100 text-green-800 text-sm">{{ session('signage_message') }}</div>
            @endif

            <x-ui-panel title="Bildschirm koppeln" subtitle="Öffne {{ url('/signage/play') }} am TV und gib den angezeigten Code ein">
                <form wire:submit="pair" class="flex flex-col sm:flex-row items-start sm:items-end gap-3 p-4">
                    <div class="w-full sm:w-40">
                        <x-ui-input-text name="pairingCode" label="Kopplungs-Code" wire:model="pairingCode" placeholder="z.B. K7P2QM" />
                        @error('pairingCode')<span class="text-xs text-red-600">{{ $message }}</span>@enderror
                    </div>
                    <div class="flex-1 w-full">
                        <x-ui-input-text name="pairingName" label="Name (optional)" wire:model="pairingName" placeholder="z.B. Empfang Foyer" />
                    </div>
                    <x-ui-button type="submit" variant="primary">Koppeln</x-ui-button>
                </form>
            </x-ui-panel>

            <x-ui-panel title="Gekoppelte Bildschirme">
                <div class="divide-y divide-[var(--ui-border)]/40">
                    @forelse($screens as $screen)
                        <div class="flex items-center justify-between p-4" wire:key="screen-{{ $screen->id }}">
                            <a href="{{ route('signage.screens.show', $screen) }}" wire:navigate class="flex items-center gap-3 flex-1">
                                <span class="inline-block w-2.5 h-2.5 rounded-full {{ $screen->isOnline() ? 'bg-green-500' : 'bg-gray-400' }}"></span>
                                <div>
                                    <div class="font-medium text-[var(--ui-secondary)]">{{ $screen->name }}</div>
                                    <div class="text-xs text-[var(--ui-muted)]">
                                        {{ $screen->isOnline() ? 'Online' : ($screen->last_seen_at ? 'Zuletzt '.$screen->last_seen_at->diffForHumans() : 'Noch nie online') }}
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
            </x-ui-panel>
        </div>
    </x-ui-page-container>
</x-ui-page>
