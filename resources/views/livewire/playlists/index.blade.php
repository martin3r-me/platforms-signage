<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="Wiedergabelisten" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'Digital Signage', 'href' => route('signage.dashboard'), 'icon' => 'tv'],
            ['label' => 'Wiedergabelisten'],
        ]">
            <x-ui-button wire:click="openCreateModal" variant="primary" size="sm">
                @svg('heroicon-o-plus', 'w-4 h-4')
                Neue Wiedergabeliste
            </x-ui-button>
        </x-ui-page-actionbar>
    </x-slot>

    <x-ui-page-container>
        <div class="space-y-6 pt-4">
            @if(session('signage_message'))
                <div class="p-3 rounded bg-green-100 text-green-800 text-sm">{{ session('signage_message') }}</div>
            @endif

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <x-signage-panel color="violet" icon="queue-list" title="Visuelle Listen">
                    @include('signage::livewire.playlists._list', ['items' => $visual])
                </x-signage-panel>
                <x-signage-panel color="violet" icon="musical-note" title="Musik-Listen">
                    @include('signage::livewire.playlists._list', ['items' => $music])
                </x-signage-panel>
            </div>
        </div>

        {{-- Dialog: Neue Wiedergabeliste --}}
        @if($showCreateModal)
            <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
                 wire:click.self="closeCreateModal" wire:key="playlist-create-modal">
                <div class="bg-[var(--ui-bg,#fff)] rounded-xl shadow-2xl w-full max-w-lg">
                    <div class="flex items-center justify-between px-5 py-3 border-b border-[var(--ui-border)]/40">
                        <h3 class="font-semibold text-[var(--ui-secondary)]">Neue Wiedergabeliste</h3>
                        <button wire:click="closeCreateModal" class="p-1 rounded text-[var(--ui-muted)] hover:text-[var(--ui-secondary)]">
                            @svg('heroicon-o-x-mark', 'w-5 h-5')
                        </button>
                    </div>
                    <form wire:submit="create" class="space-y-4 p-5">
                        <div>
                            <x-ui-input-text name="name" label="Name" wire:model="name" placeholder="z.B. Empfang Vormittag" />
                            @error('name')<span class="text-xs text-red-600">{{ $message }}</span>@enderror
                        </div>
                        <x-ui-input-select name="kind" label="Typ" wire:model="kind"
                            :options="[['value' => 'visual', 'label' => 'Visuell (Bilder/Videos/Dokumente)'], ['value' => 'music', 'label' => 'Musik (Audio)']]"
                            optionValue="value" optionLabel="label" />
                        <div class="flex items-center justify-end gap-2 pt-1">
                            <x-ui-button type="button" variant="secondary" wire:click="closeCreateModal">Abbrechen</x-ui-button>
                            <x-ui-button type="submit" variant="primary">Erstellen</x-ui-button>
                        </div>
                    </form>
                </div>
            </div>
        @endif
    </x-ui-page-container>
</x-ui-page>
