<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="Kita-Office-App" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'Digital Signage', 'href' => route('signage.dashboard'), 'icon' => 'tv'],
            ['label' => 'Medien', 'href' => route('signage.media.index')],
            ['label' => $mediaId ? 'Kita Office bearbeiten' : 'Kita Office hinzufügen'],
        ]" />
    </x-slot>

    <x-ui-page-container>
        <div class="max-w-3xl mx-auto space-y-6 pt-4"
             x-data="{
                cfg: @entangle('config'),
                send() {
                    const f = $refs.frame;
                    if (!f || !f.contentWindow) return;
                    const config = JSON.parse(JSON.stringify(this.cfg));
                    config.endpoint = @js($dataEndpoint);
                    config.preview_config = true;
                    f.contentWindow.postMessage({ __signagePreview: true, app_type: 'kitaoffice', config: config }, '*');
                },
                addRow(list, row) { this.cfg[list] = (this.cfg[list] || []).concat([row]); this.send(); },
                removeRow(list, i) { this.cfg[list].splice(i, 1); this.send(); },
                csv(list) { return (this.cfg[list] || []).join(', '); },
                setCsv(list, v) { this.cfg[list] = v.split(',').map(s => s.trim()).filter(Boolean); },
                init() {
                    window.addEventListener('message', (e) => { if (e.data && e.data.__signagePreviewReady) this.send(); });
                }
             }"
             x-on:input.debounce.400ms="send()">

            <x-signage-panel icon="eye" title="Vorschau" subtitle="Live-Daten mit deinen Verbindungen">
                <div class="p-4">
                    <div class="relative w-full mx-auto aspect-video rounded-lg overflow-hidden border border-[var(--ui-border)]/40 bg-black">
                        <iframe x-ref="frame" src="{{ route('signage.apps.preview-live') }}" class="absolute inset-0 w-full h-full" x-on:load="send()"></iframe>
                    </div>
                </div>
            </x-signage-panel>

            <form wire:submit="save" class="space-y-6">
                <x-signage-panel icon="building-office" title="Allgemein" subtitle="Kopfzeile mit Datum, KW, Wetter und Uhr">
                    <div class="space-y-4 p-4">
                        <div>
                            <x-ui-input-text name="name" label="Name (intern)" wire:model="name" placeholder="z.B. Kita Office Büro" />
                            @error('name')<span class="text-xs text-red-600">{{ $message }}</span>@enderror
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-[var(--ui-secondary)] mb-1">Überschrift</label>
                                <input type="text" x-model="cfg.title" class="w-full px-3 py-2 rounded-lg border border-[var(--ui-border)] text-sm">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-[var(--ui-secondary)] mb-1">Darstellung</label>
                                <select x-model="cfg.theme" x-on:change="send()" class="w-full px-3 py-2 rounded-lg border border-[var(--ui-border)] bg-white text-sm">
                                    <option value="dark">Dunkel</option>
                                    <option value="light">Hell</option>
                                    <option value="auto">Automatisch (hell 7–19 Uhr)</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-[var(--ui-secondary)] mb-1">Wetter-Ort</label>
                                <input type="text" x-model="cfg.city" class="w-full px-3 py-2 rounded-lg border border-[var(--ui-border)] text-sm">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-[var(--ui-secondary)] mb-1">Breitengrad</label>
                                <input type="number" step="0.01" x-model.number="cfg.latitude" class="w-full px-3 py-2 rounded-lg border border-[var(--ui-border)] text-sm">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-[var(--ui-secondary)] mb-1">Längengrad</label>
                                <input type="number" step="0.01" x-model.number="cfg.longitude" class="w-full px-3 py-2 rounded-lg border border-[var(--ui-border)] text-sm">
                            </div>
                        </div>
                    </div>
                </x-signage-panel>

                <x-signage-panel icon="fire" title="CoolCooking-Boxen" subtitle="Gerätestatus aus einer MeinConnect-Gastfreigabe">
                    <div class="space-y-4 p-4">
                        @unless($meinconnectPresent)
                            <div class="p-3 rounded bg-amber-50 border border-amber-200 text-amber-800 text-sm">
                                Die MeinConnect-Integration ist hier nicht aktiv – der Bereich bleibt leer.
                            </div>
                        @endunless
                        <div>
                            <label class="block text-sm font-medium text-[var(--ui-secondary)] mb-1">MeinConnect-Verbindung</label>
                            <select x-model="cfg.meinconnect_connection_id" x-on:change="send()"
                                    class="w-full px-3 py-2 rounded-lg border border-[var(--ui-border)] bg-white text-sm">
                                <option value="">– keine Boxen anzeigen –</option>
                                @foreach($meinconnectConnections as $c)
                                    <option value="{{ $c['id'] }}">{{ $c['label'] }}</option>
                                @endforeach
                            </select>
                            @if(empty($meinconnectConnections))
                                <span class="text-xs text-[var(--ui-muted)]">
                                    Noch keine Verbindung. Den Freigabelink im Integrationen-Bereich unter „MeinConnect“ hinterlegen.
                                </span>
                            @endif
                        </div>
                        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                            <div>
                                <label class="block text-xs font-medium text-[var(--ui-secondary)] mb-1">Boxen je Seite</label>
                                <input type="number" min="1" max="8" x-model.number="cfg.boxes_per_page" class="w-full px-3 py-2 rounded-lg border border-[var(--ui-border)] text-sm">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-[var(--ui-secondary)] mb-1">Seitenwechsel (Sek.)</label>
                                <input type="number" min="5" max="300" x-model.number="cfg.box_page_seconds" class="w-full px-3 py-2 rounded-lg border border-[var(--ui-border)] text-sm">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-[var(--ui-secondary)] mb-1">Offline nach (Min.)</label>
                                <input type="number" min="1" max="120" x-model.number="cfg.offline_minutes" class="w-full px-3 py-2 rounded-lg border border-[var(--ui-border)] text-sm">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-[var(--ui-secondary)] mb-1">Tür rot nach (Min.)</label>
                                <input type="number" min="1" max="120" x-model.number="cfg.door_warning_minutes" class="w-full px-3 py-2 rounded-lg border border-[var(--ui-border)] text-sm">
                            </div>
                        </div>
                        <div class="w-48">
                            <label class="block text-xs font-medium text-[var(--ui-secondary)] mb-1">Türwarnung nur bis</label>
                            <input type="time" x-model="cfg.door_warning_until" class="w-full px-3 py-2 rounded-lg border border-[var(--ui-border)] text-sm">
                        </div>

                        <div>
                            <div class="text-sm font-medium text-[var(--ui-secondary)] mb-1">Telefon der Einrichtungen</div>
                            <div class="text-xs text-[var(--ui-muted)] mb-2">Wird an jeder Box angezeigt, deren Name den Suchbegriff enthält.</div>
                            <template x-for="(row, i) in cfg.facility_phones" :key="'f'+i">
                                <div class="flex gap-2 mb-2">
                                    <input type="text" x-model="row.match" placeholder="Name enthält …" class="flex-1 px-3 py-1.5 rounded-lg border border-[var(--ui-border)] text-sm">
                                    <input type="text" x-model="row.phone" placeholder="Telefon" class="w-44 px-3 py-1.5 rounded-lg border border-[var(--ui-border)] text-sm">
                                    <button type="button" x-on:click="removeRow('facility_phones', i)" class="px-2 text-[var(--ui-muted)] hover:text-red-600">@svg('heroicon-o-trash', 'w-4 h-4')</button>
                                </div>
                            </template>
                            <x-ui-button type="button" variant="secondary" size="sm" x-on:click="addRow('facility_phones', {match: '', phone: ''})">Einrichtung hinzufügen</x-ui-button>
                        </div>
                    </div>
                </x-signage-panel>

                <x-signage-panel icon="truck" title="Touren" subtitle="Heutige Touren aus DedeFleet mit Fahrer-Telefon">
                    <div class="space-y-4 p-4">
                        @unless($dedefleetPresent)
                            <div class="p-3 rounded bg-amber-50 border border-amber-200 text-amber-800 text-sm">
                                Die DedeFleet-Integration ist hier nicht aktiv – der Bereich bleibt leer.
                            </div>
                        @endunless
                        <div>
                            <label class="block text-sm font-medium text-[var(--ui-secondary)] mb-1">DedeFleet-Verbindung</label>
                            <select x-model="cfg.dedefleet_connection_id" x-on:change="send()"
                                    class="w-full px-3 py-2 rounded-lg border border-[var(--ui-border)] bg-white text-sm">
                                <option value="">– keine Touren anzeigen –</option>
                                @foreach($dedefleetConnections as $c)
                                    <option value="{{ $c['id'] }}">{{ $c['label'] }}</option>
                                @endforeach
                            </select>
                        </div>
                        <label class="inline-flex items-center gap-2 text-sm cursor-pointer">
                            <input type="checkbox" x-model="cfg.hide_pickups" x-on:change="send()" class="rounded">
                            Abholungen ausblenden
                        </label>

                        <div>
                            <div class="text-sm font-medium text-[var(--ui-secondary)] mb-1">Telefonliste der Fahrer</div>
                            <div class="text-xs text-[var(--ui-muted)] mb-2">
                                Übergangsweise manuell. Später wird hier die „Telefonliste Logistik“ aus SharePoint angebunden.
                                Fehlende oder mehrdeutige Treffer werden auf dem Bildschirm markiert, nie geraten.
                            </div>
                            <template x-for="(row, i) in cfg.drivers" :key="'d'+i">
                                <div class="flex gap-2 mb-2">
                                    <input type="text" x-model="row.name" placeholder="Fahrer" class="flex-1 px-3 py-1.5 rounded-lg border border-[var(--ui-border)] text-sm">
                                    <input type="text" x-model="row.phone" placeholder="Telefon" class="w-44 px-3 py-1.5 rounded-lg border border-[var(--ui-border)] text-sm">
                                    <button type="button" x-on:click="removeRow('drivers', i)" class="px-2 text-[var(--ui-muted)] hover:text-red-600">@svg('heroicon-o-trash', 'w-4 h-4')</button>
                                </div>
                            </template>
                            <x-ui-button type="button" variant="secondary" size="sm" x-on:click="addRow('drivers', {name: '', phone: ''})">Fahrer hinzufügen</x-ui-button>
                        </div>

                        <div>
                            <div class="text-sm font-medium text-[var(--ui-secondary)] mb-1">Namens-Zuordnungen</div>
                            <div class="text-xs text-[var(--ui-muted)] mb-2">Abweichende Schreibweisen im Tourenplan auf einen Namen abbilden (Groß-/Kleinschreibung, Punkte und Leerzeichen egal).</div>
                            <template x-for="(row, i) in cfg.driver_aliases" :key="'a'+i">
                                <div class="flex gap-2 mb-2 items-center">
                                    <input type="text" x-model="row.from" placeholder="im Plan" class="flex-1 px-3 py-1.5 rounded-lg border border-[var(--ui-border)] text-sm">
                                    <span class="text-[var(--ui-muted)]">→</span>
                                    <input type="text" x-model="row.to" placeholder="anzeigen als" class="flex-1 px-3 py-1.5 rounded-lg border border-[var(--ui-border)] text-sm">
                                    <button type="button" x-on:click="removeRow('driver_aliases', i)" class="px-2 text-[var(--ui-muted)] hover:text-red-600">@svg('heroicon-o-trash', 'w-4 h-4')</button>
                                </div>
                            </template>
                            <x-ui-button type="button" variant="secondary" size="sm" x-on:click="addRow('driver_aliases', {from: '', to: ''})">Zuordnung hinzufügen</x-ui-button>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-[var(--ui-secondary)] mb-1">„Nur über WhatsApp“</label>
                                <input type="text" :value="csv('whatsapp_only')" x-on:change="setCsv('whatsapp_only', $event.target.value); send()"
                                       placeholder="Hicham, Salim" class="w-full px-3 py-2 rounded-lg border border-[var(--ui-border)] text-sm">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-[var(--ui-secondary)] mb-1">Dienstleister-Präfixe entfernen</label>
                                <input type="text" :value="csv('strip_prefixes')" x-on:change="setCsv('strip_prefixes', $event.target.value); send()"
                                       placeholder="Onway" class="w-full px-3 py-2 rounded-lg border border-[var(--ui-border)] text-sm">
                            </div>
                        </div>
                    </div>
                </x-signage-panel>

                <div class="flex items-center gap-2">
                    <x-ui-button type="submit" variant="primary">Speichern</x-ui-button>
                    <x-ui-button variant="secondary" :href="route('signage.media.index')">Abbrechen</x-ui-button>
                </div>
            </form>
        </div>
    </x-ui-page-container>
</x-ui-page>
