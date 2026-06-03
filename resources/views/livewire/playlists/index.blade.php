<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="Wiedergabelisten" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'Digital Signage', 'href' => route('signage.dashboard'), 'icon' => 'tv'],
            ['label' => 'Wiedergabelisten'],
        ]" />
    </x-slot>

    <x-ui-page-container>
        <div class="space-y-6">
            @if(session('signage_message'))
                <div class="p-3 rounded bg-green-100 text-green-800 text-sm">{{ session('signage_message') }}</div>
            @endif

            <x-signage-panel icon="plus-circle" title="Neue Wiedergabeliste">
                <form wire:submit="create" class="flex flex-col sm:flex-row items-start sm:items-end gap-3 p-4">
                    <div class="flex-1 w-full">
                        <x-ui-input-text name="name" label="Name" wire:model="name" placeholder="z.B. Empfang Vormittag" />
                        @error('name')<span class="text-xs text-red-600">{{ $message }}</span>@enderror
                    </div>
                    <div class="w-full sm:w-56">
                        <x-ui-input-select name="kind" label="Typ" wire:model="kind"
                            :options="[['value' => 'visual', 'label' => 'Visuell (Bilder/Videos/Dokumente)'], ['value' => 'music', 'label' => 'Musik (Audio)']]"
                            optionValue="value" optionLabel="label" />
                    </div>
                    <x-ui-button type="submit" variant="primary">Erstellen</x-ui-button>
                </form>
            </x-signage-panel>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <x-signage-panel icon="queue-list" title="Visuelle Listen">
                    @include('signage::livewire.playlists._list', ['items' => $visual])
                </x-signage-panel>
                <x-signage-panel icon="musical-note" title="Musik-Listen">
                    @include('signage::livewire.playlists._list', ['items' => $music])
                </x-signage-panel>
            </div>
        </div>
    </x-ui-page-container>
</x-ui-page>
