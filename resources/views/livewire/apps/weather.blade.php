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
        <div class="max-w-2xl mx-auto space-y-6 pt-4" x-data="{
                send() {
                    const f = $refs.frame;
                    if (!f || !f.contentWindow) return;
                    const lat = $wire.get('latitude') || 52.52;
                    const lon = $wire.get('longitude') || 13.405;
                    f.contentWindow.postMessage({
                        __signagePreview: true, app_type: 'weather',
                        config: {
                            design: $wire.get('design'), color_scheme: $wire.get('colorScheme'),
                            units: $wire.get('units'), latitude: lat, longitude: lon,
                            location_name: $wire.get('resolvedName') || $wire.get('locationQuery') || 'Vorschau',
                        }
                    }, '*');
                },
                init() {
                    ['design','colorScheme','units','resolvedName']
                        .forEach(p => this.$wire.$watch(p, () => this.send()));
                    window.addEventListener('message', (e) => { if (e.data && e.data.__signagePreviewReady) this.send(); });
                }
            }">
            <x-signage-panel color="sky" icon="eye" title="Vorschau" subtitle="Design & Farbschema live ansehen (nutzt Beispiel-Ort, bis gespeichert)">
                <div class="p-4">
                    <div class="relative w-full max-w-md mx-auto aspect-video rounded-lg overflow-hidden border border-[var(--ui-border)]/40 bg-black">
                        <iframe x-ref="frame" src="{{ route('signage.apps.preview-live') }}" class="absolute inset-0 w-full h-full" x-on:load="send()"></iframe>
                    </div>
                </div>
            </x-signage-panel>

            <x-signage-panel color="sky" icon="cloud" title="Wetter" subtitle="Live-Wetter & 7-Tage-Vorhersage (Open-Meteo, kein API-Key)">
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
                        <x-ui-input-select name="design" label="Design" wire:model.live="design"
                            :options="[
                                ['value' => 'modern', 'label' => 'Modern (Vollbild)'],
                                ['value' => 'compact', 'label' => 'Kompakt (Karte)'],
                            ]"
                            optionValue="value" optionLabel="label" />

                        <x-ui-input-select name="colorScheme" label="Farbschema / Thema" wire:model.live="colorScheme"
                            :options="[
                                ['value' => 'sky', 'label' => 'Sky (Blau)'],
                                ['value' => 'sage', 'label' => 'Sage (Grün)'],
                                ['value' => 'light', 'label' => 'Hell'],
                                ['value' => 'dark', 'label' => 'Dunkel'],
                            ]"
                            optionValue="value" optionLabel="label" />

                        <x-ui-input-select name="units" label="Einheiten" wire:model.live="units"
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
            </x-signage-panel>
        </div>
    </x-ui-page-container>
</x-ui-page>
