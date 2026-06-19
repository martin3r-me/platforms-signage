<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="Veranstaltungs-App" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'Digital Signage', 'href' => route('signage.dashboard'), 'icon' => 'tv'],
            ['label' => 'Medien', 'href' => route('signage.media.index')],
            ['label' => $mediaId ? 'Veranstaltungen bearbeiten' : 'Veranstaltungen hinzufügen'],
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
                    f.contentWindow.postMessage({ __signagePreview: true, app_type: 'events', config: config }, '*');
                },
                init() {
                    window.addEventListener('message', (e) => { if (e.data && e.data.__signagePreviewReady) this.send(); });
                }
             }"
             x-on:input.debounce.150ms="send()">

            <x-signage-panel icon="eye" title="Vorschau" subtitle="Zeigt echte Veranstaltungen deines Teams">
                <div class="p-4">
                    <div class="relative w-full max-w-md mx-auto aspect-video rounded-lg overflow-hidden border border-[var(--ui-border)]/40 bg-black">
                        <iframe x-ref="frame" src="{{ route('signage.apps.preview-live') }}" class="absolute inset-0 w-full h-full" x-on:load="send()"></iframe>
                    </div>
                </div>
            </x-signage-panel>

            <x-signage-panel icon="calendar-days" title="Veranstaltungen" subtitle="Heutige/kommende Buchungen aus dem Events-Modul">
                <form wire:submit="save" class="space-y-5 p-4">
                    @unless($eventsAvailable)
                        <div class="p-3 rounded bg-amber-50 border border-amber-200 text-amber-800 text-sm">
                            Hinweis: Das Events-Modul ist hier nicht aktiv – das Board bleibt leer, bis es verfügbar ist.
                        </div>
                    @endunless

                    <div>
                        <x-ui-input-text name="name" label="Name (intern)" wire:model="name" placeholder="z.B. Foyer Belegung" />
                        @error('name')<span class="text-xs text-red-600">{{ $message }}</span>@enderror
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-[var(--ui-secondary)] mb-1">Überschrift</label>
                            <input type="text" x-model="cfg.title" class="w-full px-3 py-2 rounded-lg border border-[var(--ui-border)] text-sm">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-[var(--ui-secondary)] mb-1">Thema</label>
                            <select x-model="cfg.theme" x-on:change="send()" class="w-full px-3 py-2 rounded-lg border border-[var(--ui-border)] bg-white text-sm">
                                <option value="dark">Dunkel</option>
                                <option value="light">Hell</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-[var(--ui-secondary)] mb-1">Zeitraum</label>
                            <select x-model.number="cfg.days" x-on:change="send()" class="w-full px-3 py-2 rounded-lg border border-[var(--ui-border)] bg-white text-sm">
                                <option :value="1">Heute</option>
                                <option :value="3">3 Tage</option>
                                <option :value="7">7 Tage</option>
                            </select>
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-[var(--ui-secondary)] mb-2">Status-Filter (leer = alle)</label>
                        <div class="flex flex-wrap gap-3">
                            @foreach($statuses as $s)
                                <label class="inline-flex items-center gap-2 text-sm cursor-pointer">
                                    <input type="checkbox" value="{{ $s }}" x-model="cfg.status" x-on:change="send()" class="rounded">
                                    {{ $s }}
                                </label>
                            @endforeach
                        </div>
                    </div>

                    <div class="flex items-center gap-2 pt-2">
                        <x-ui-button type="submit" variant="primary">Speichern</x-ui-button>
                        <x-ui-button variant="secondary" :href="route('signage.media.index')">Abbrechen</x-ui-button>
                    </div>
                </form>
            </x-signage-panel>
        </div>
    </x-ui-page-container>
</x-ui-page>
