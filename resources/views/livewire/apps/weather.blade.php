<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="Wetter-App" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'Digital Signage', 'href' => route('signage.dashboard'), 'icon' => 'tv'],
            ['label' => 'Medien', 'href' => route('signage.media.index')],
            ['label' => $mediaId ? 'Wetter bearbeiten' : 'Wetter hinzufügen'],
        ]" />
    </x-slot>

    <x-ui-page-container>
        <div class="max-w-2xl mx-auto space-y-6">
            <x-ui-panel title="Wetter" subtitle="Live-Wetter & 7-Tage-Vorhersage (Open-Meteo, kein API-Key)">
                <form wire:submit="save" class="space-y-5 p-4">
                    <div>
                        <x-ui-input-text name="name" label="Name" wire:model="name" placeholder="z.B. Wetter Empfang" />
                        @error('name')<span class="text-xs text-red-600">{{ $message }}</span>@enderror
                    </div>

                    <div>
                        <x-ui-input-text name="locationQuery" label="Ort" wire:model="locationQuery" placeholder="z.B. Köln, Houston, Hawaii" />
                        @error('locationQuery')<span class="text-xs text-red-600">{{ $message }}</span>@enderror
                        @if($resolvedName)
                            <span class="text-xs text-[var(--ui-muted)]">Erkannt: {{ $resolvedName }} ({{ $latitude }}, {{ $longitude }})</span>
                        @endif
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <x-ui-input-select name="design" label="Design" wire:model="design"
                            :options="[
                                ['value' => 'modern', 'label' => 'Modern (Vollbild)'],
                                ['value' => 'compact', 'label' => 'Kompakt (Karte)'],
                            ]"
                            optionValue="value" optionLabel="label" />

                        <x-ui-input-select name="colorScheme" label="Farbschema / Thema" wire:model="colorScheme"
                            :options="[
                                ['value' => 'sky', 'label' => 'Sky (Blau)'],
                                ['value' => 'sage', 'label' => 'Sage (Grün)'],
                                ['value' => 'light', 'label' => 'Hell'],
                                ['value' => 'dark', 'label' => 'Dunkel'],
                            ]"
                            optionValue="value" optionLabel="label" />

                        <x-ui-input-select name="units" label="Einheiten" wire:model="units"
                            :options="[
                                ['value' => 'metric', 'label' => '°C, km/h'],
                                ['value' => 'imperial', 'label' => '°F, mph'],
                            ]"
                            optionValue="value" optionLabel="label" />
                    </div>

                    <p class="text-xs text-[var(--ui-muted)]">
                        Hochformat-Bildschirme zeigen die Vorhersage automatisch als vertikale Liste.
                    </p>

                    <div class="flex items-center gap-2 pt-2">
                        <x-ui-button type="submit" variant="primary">Speichern</x-ui-button>
                        <x-ui-button variant="secondary" :href="route('signage.media.index')">Abbrechen</x-ui-button>
                    </div>
                </form>
            </x-ui-panel>
        </div>
    </x-ui-page-container>
</x-ui-page>
