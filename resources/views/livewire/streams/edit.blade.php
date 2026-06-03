<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar :title="$mediaId ? 'Stream bearbeiten' : 'Stream einbinden'" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'Digital Signage', 'href' => route('signage.dashboard'), 'icon' => 'tv'],
            ['label' => 'Medien', 'href' => route('signage.media.index')],
            ['label' => $mediaId ? 'Stream bearbeiten' : 'Stream einbinden'],
        ]" />
    </x-slot>

    <x-ui-page-container>
        <div class="max-w-2xl mx-auto space-y-6 pt-4">
            <x-signage-panel color="emerald" icon="signal" title="Stream" subtitle="Name, URL und Typ ändern">
                <form wire:submit="save" class="space-y-5 p-4">
                    <div>
                        <x-ui-input-text name="name" label="Name" wire:model="name" />
                        @error('name')<span class="text-xs text-red-600">{{ $message }}</span>@enderror
                    </div>
                    <div>
                        <x-ui-input-text name="url" label="URL" wire:model="url"
                            placeholder="https://… (Stream-URL oder TuneIn-Link)" />
                        @error('url')<span class="text-xs text-red-600">{{ $message }}</span>@enderror
                    </div>
                    <div class="sm:w-1/2">
                        <x-ui-input-select name="type" label="Typ" wire:model="type"
                            :options="[
                                ['value' => 'stream', 'label' => 'Direkter Stream (empfohlen, autoplay)'],
                                ['value' => 'embed', 'label' => 'Eingebetteter Player / iframe'],
                            ]"
                            optionValue="value" optionLabel="label" />
                    </div>
                    <p class="text-xs text-[var(--ui-muted)]">
                        TuneIn-Links werden beim Speichern automatisch in einen direkten Stream aufgelöst.
                    </p>
                    <div class="flex items-center gap-2 pt-2">
                        <x-ui-button type="submit" variant="primary">Speichern</x-ui-button>
                        <x-ui-button variant="secondary" :href="route('signage.media.index')">Abbrechen</x-ui-button>
                    </div>
                </form>
            </x-signage-panel>
        </div>
    </x-ui-page-container>
</x-ui-page>
