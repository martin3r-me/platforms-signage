<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="Digital Signage" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'Digital Signage', 'href' => route('signage.dashboard'), 'icon' => 'tv'],
        ]">
            <div class="flex items-center gap-2">
                <x-ui-button variant="secondary" size="sm" :href="route('signage.media.index')">
                    @svg('heroicon-o-photo', 'w-4 h-4')
                    Medien
                </x-ui-button>
                <x-ui-button variant="secondary" size="sm" :href="route('signage.playlists.index')">
                    @svg('heroicon-o-queue-list', 'w-4 h-4')
                    Wiedergabelisten
                </x-ui-button>
                <x-ui-button variant="primary" size="sm" :href="route('signage.screens.index')">
                    @svg('heroicon-o-computer-desktop', 'w-4 h-4')
                    Bildschirme
                </x-ui-button>
            </div>
        </x-ui-page-actionbar>
    </x-slot>

    <x-ui-page-container>
        <div class="space-y-6">
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
                <x-ui-dashboard-tile title="Bildschirme" :count="$stats['screens']" subtitle="Gekoppelt" icon="computer-desktop" variant="secondary" size="lg" />
                <x-ui-dashboard-tile title="Online" :count="$stats['online']" subtitle="Gerade erreichbar" icon="signal" variant="secondary" size="lg" />
                <x-ui-dashboard-tile title="Medien" :count="$stats['media']" subtitle="In der Bibliothek" icon="photo" variant="secondary" size="lg" />
                <x-ui-dashboard-tile title="Wiedergabelisten" :count="$stats['playlists']" subtitle="Angelegt" icon="queue-list" variant="secondary" size="lg" />
            </div>

            <x-ui-panel title="Bildschirme" subtitle="Status der gekoppelten Geräte">
                <div class="divide-y divide-[var(--ui-border)]/40">
                    @forelse($screens as $screen)
                        <a href="{{ route('signage.screens.show', $screen) }}" wire:navigate class="flex items-center justify-between p-4 hover:bg-[var(--ui-muted-5)]">
                            <div class="flex items-center gap-3">
                                <span class="inline-block w-2.5 h-2.5 rounded-full {{ $screen->isOnline() ? 'bg-green-500' : 'bg-gray-400' }}"></span>
                                <span class="font-medium text-[var(--ui-secondary)]">{{ $screen->name }}</span>
                            </div>
                            <div class="text-sm text-[var(--ui-muted)]">
                                {{ $screen->isOnline() ? 'Online' : ($screen->last_seen_at ? 'Zuletzt '.$screen->last_seen_at->diffForHumans() : 'Noch nie online') }}
                            </div>
                        </a>
                    @empty
                        <div class="p-6 text-center text-[var(--ui-muted)]">
                            Noch keine Bildschirme gekoppelt.
                            <a href="{{ route('signage.screens.index') }}" wire:navigate class="text-[var(--ui-primary)] underline">Jetzt koppeln</a>
                        </div>
                    @endforelse
                </div>
            </x-ui-panel>
        </div>
    </x-ui-page-container>
</x-ui-page>
