<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="Apps" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'Digital Signage', 'href' => route('signage.dashboard'), 'icon' => 'tv'],
            ['label' => 'Apps'],
        ]">
            <div class="flex items-center gap-2">
                <x-ui-button variant="secondary" size="sm" :href="route('signage.apps.clock.create')">
                    @svg('heroicon-o-clock', 'w-4 h-4')
                    Uhr
                </x-ui-button>
                <x-ui-button variant="secondary" size="sm" :href="route('signage.apps.weather.create')">
                    @svg('heroicon-o-cloud', 'w-4 h-4')
                    Wetter
                </x-ui-button>
                <x-ui-button variant="secondary" size="sm" :href="route('signage.apps.menu.create')">
                    @svg('heroicon-o-queue-list', 'w-4 h-4')
                    Menü
                </x-ui-button>
                <x-ui-button variant="secondary" size="sm" :href="route('signage.apps.events.create')">
                    @svg('heroicon-o-calendar-days', 'w-4 h-4')
                    Veranstaltungen
                </x-ui-button>
            </div>
        </x-ui-page-actionbar>
    </x-slot>

    <x-ui-page-container>
        <div class="space-y-4 pt-4">
            @if(session('signage_message'))
                <div class="p-3 rounded bg-green-100 text-green-800 text-sm">{{ session('signage_message') }}</div>
            @endif

            <x-signage-panel color="violet" icon="squares-2x2" title="Apps" subtitle="Dynamische Inhalte – auch in der Medienbibliothek für Playlists verfügbar">
                @if($apps->isEmpty())
                    <div class="p-10 text-center text-[var(--ui-muted)]">
                        @svg('heroicon-o-squares-2x2', 'w-8 h-8 mx-auto mb-2 opacity-60')
                        Noch keine Apps. Oben über die Buttons eine Uhr-, Wetter-, Menü- oder Veranstaltungs-App anlegen.
                    </div>
                @else
                    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-6 gap-4 p-4">
                        @foreach($apps as $app)
                            <div class="group relative rounded-lg overflow-hidden border border-[var(--ui-border)]/40 bg-[var(--ui-muted-5)] flex flex-col h-full" wire:key="app-{{ $app->id }}">
                                <div class="w-full bg-black/5 shrink-0 overflow-hidden" style="aspect-ratio: 16 / 9">
                                    <iframe src="{{ route('signage.apps.preview', $app) }}" class="w-full h-full border-0 pointer-events-none" scrolling="no" loading="lazy" tabindex="-1"></iframe>
                                </div>
                                <div class="p-2 flex-1 flex flex-col">
                                    <div class="text-xs font-medium text-[var(--ui-secondary)] truncate" title="{{ $app->name }}">{{ $app->name }}</div>
                                    <div class="mt-1.5">
                                        <x-signage-badge color="violet">{{ $app->appTypeLabel() }}</x-signage-badge>
                                    </div>
                                </div>
                                <a href="{{ route('signage.apps.'.$app->app_type.'.edit', $app) }}" wire:navigate
                                   title="Bearbeiten"
                                   class="absolute top-1.5 right-9 p-1 rounded bg-black/50 text-white opacity-0 group-hover:opacity-100 transition">
                                    @svg('heroicon-o-pencil-square', 'w-4 h-4')
                                </a>
                                <button wire:click="deleteApp({{ $app->id }})"
                                        wire:confirm="App „{{ $app->name }}“ wirklich löschen?"
                                        class="absolute top-1.5 right-1.5 p-1 rounded bg-black/50 text-white opacity-0 group-hover:opacity-100 transition">
                                    @svg('heroicon-o-trash', 'w-4 h-4')
                                </button>
                            </div>
                        @endforeach
                    </div>
                @endif
            </x-signage-panel>
        </div>
    </x-ui-page-container>
</x-ui-page>
