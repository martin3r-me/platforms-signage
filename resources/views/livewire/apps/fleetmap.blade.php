<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="Fahrzeug-Karte" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'Digital Signage', 'href' => route('signage.dashboard'), 'icon' => 'tv'],
            ['label' => 'Medien', 'href' => route('signage.media.index')],
            ['label' => $mediaId ? 'Fahrzeug-Karte bearbeiten' : 'Fahrzeug-Karte hinzufügen'],
        ]" />
    </x-slot>

    <x-ui-page-container>
        <div class="max-w-2xl mx-auto space-y-6 pt-4"
             x-data="{
                cfg: @entangle('config'),
                send() {
                    const f = $refs.frame;
                    if (!f || !f.contentWindow) return;
                    const config = JSON.parse(JSON.stringify(this.cfg));
                    config.endpoint = @js($dataEndpoint);
                    f.contentWindow.postMessage({ __signagePreview: true, app_type: 'fleetmap', config: config }, '*');
                },
                init() {
                    window.addEventListener('message', (e) => { if (e.data && e.data.__signagePreviewReady) this.send(); });
                }
             }"
             x-on:input.debounce.150ms="send()">

            <x-signage-panel icon="eye" title="Vorschau" subtitle="Live-Standorte der Fahrzeuge deines Teams">
                <div class="p-4">
                    <div class="relative w-full max-w-md mx-auto aspect-video rounded-lg overflow-hidden border border-[var(--ui-border)]/40 bg-black">
                        <iframe x-ref="frame" src="{{ route('signage.apps.preview-live') }}" class="absolute inset-0 w-full h-full" x-on:load="send()"></iframe>
                    </div>
                </div>
            </x-signage-panel>

            <x-signage-panel icon="map-pin" title="Fahrzeug-Karte" subtitle="Live-GPS aus DedeFleet auf einer Karte">
                <form wire:submit="save" class="space-y-5 p-4">
                    @unless($integrationPresent)
                        <div class="p-3 rounded bg-amber-50 border border-amber-200 text-amber-800 text-sm">
                            Hinweis: Die DedeFleet-Integration ist hier nicht aktiv – die Karte bleibt leer, bis sie verfügbar ist.
                        </div>
                    @endunless

                    <div>
                        <x-ui-input-text name="name" label="Name (intern)" wire:model="name" placeholder="z.B. Fahrzeug-Karte Halle" />
                        @error('name')<span class="text-xs text-red-600">{{ $message }}</span>@enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-[var(--ui-secondary)] mb-1">DedeFleet-Verbindung</label>
                        <select x-model="cfg.connection_id" x-on:change="send()"
                                class="w-full px-3 py-2 rounded-lg border border-[var(--ui-border)] bg-white text-sm">
                            <option value="">– Verbindung wählen –</option>
                            @foreach($connections as $c)
                                <option value="{{ $c['id'] }}">{{ $c['label'] }}</option>
                            @endforeach
                        </select>
                        @if(empty($connections))
                            <span class="text-xs text-[var(--ui-muted)]">
                                Noch keine DedeFleet-Verbindung vorhanden. Lege sie zuerst im Integrationen-Bereich an.
                            </span>
                        @endif
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-[var(--ui-secondary)] mb-1">Überschrift</label>
                            <input type="text" x-model="cfg.title" class="w-full px-3 py-2 rounded-lg border border-[var(--ui-border)] text-sm" placeholder="Fahrzeuge">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-[var(--ui-secondary)] mb-1">Stil (Karte hell/dunkel)</label>
                            <select x-model="cfg.style" x-on:change="send()" class="w-full px-3 py-2 rounded-lg border border-[var(--ui-border)] bg-white text-sm">
                                <option value="modern">Modern (hell, Teal)</option>
                                <option value="warm">Warm (creme, hell)</option>
                                <option value="elegant">Elegant (dunkel, Gold)</option>
                                <option value="night">Night (dunkelblau)</option>
                            </select>
                        </div>
                    </div>

                    <div class="flex flex-wrap gap-5">
                        <label class="inline-flex items-center gap-2 text-sm cursor-pointer">
                            <input type="checkbox" x-model="cfg.show_clock" x-on:change="send()" class="rounded">
                            Uhrzeit anzeigen
                        </label>
                        <label class="inline-flex items-center gap-2 text-sm cursor-pointer">
                            <input type="checkbox" x-model="cfg.show_temp" x-on:change="send()" class="rounded">
                            Kühlketten-Temperatur am Marker
                        </label>
                    </div>

                    <p class="text-xs text-[var(--ui-muted)]">
                        Fahrzeuge erscheinen nur, wenn sie gerade senden (während der Touren). Außerhalb der Fahrzeiten bleibt die Karte leer.
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
