<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="Menü-App" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'Digital Signage', 'href' => route('signage.dashboard'), 'icon' => 'tv'],
            ['label' => 'Medien', 'href' => route('signage.media.index')],
            ['label' => $mediaId ? 'Menü bearbeiten' : 'Menü hinzufügen'],
        ]" />
    </x-slot>

    <x-ui-page-container>
        <div class="max-w-4xl mx-auto space-y-6 pt-4"
             x-data="{
                cfg: @entangle('config'),
                send() {
                    const f = $refs.frame;
                    if (!f || !f.contentWindow) return;
                    f.contentWindow.postMessage({
                        __signagePreview: true, app_type: 'menu',
                        config: JSON.parse(JSON.stringify(this.cfg)),
                    }, '*');
                },
                init() {
                    window.addEventListener('message', (e) => { if (e.data && e.data.__signagePreviewReady) this.send(); });
                }
             }"
             x-on:input.debounce.150ms="send()">

            <x-signage-panel icon="eye" title="Vorschau" subtitle="Aktualisiert sich live beim Ändern">
                <div class="p-4">
                    <div class="relative w-full max-w-md mx-auto aspect-video rounded-lg overflow-hidden border border-[var(--ui-border)]/40 bg-black">
                        <iframe x-ref="frame" src="{{ route('signage.apps.preview-live') }}" class="absolute inset-0 w-full h-full" x-on:load="send()"></iframe>
                    </div>
                </div>
            </x-signage-panel>

            <x-signage-panel icon="queue-list" title="Menü" subtitle="Kategorien, Einträge und Preise">
                <form wire:submit="save" class="space-y-5 p-4">
                    <div>
                        <x-ui-input-text name="name" label="Name (intern)" wire:model="name" placeholder="z.B. Mittagskarte" />
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
                            <label class="block text-sm font-medium text-[var(--ui-secondary)] mb-1">Spalten</label>
                            <select x-model.number="cfg.columns" x-on:change="send()" class="w-full px-3 py-2 rounded-lg border border-[var(--ui-border)] bg-white text-sm">
                                <option :value="1">1 Spalte</option>
                                <option :value="2">2 Spalten</option>
                            </select>
                        </div>
                    </div>

                    {{-- Kategorien --}}
                    <div class="space-y-4 pt-2 border-t border-[var(--ui-border)]/40">
                        <template x-for="(cat, ci) in cfg.categories" :key="ci">
                            <div class="rounded-lg border border-[var(--ui-border)] p-3 space-y-3">
                                <div class="flex items-center gap-2">
                                    <input type="text" x-model="cat.name" placeholder="Kategorie (z.B. Vorspeisen)"
                                           class="flex-1 px-2 py-1.5 rounded border border-[var(--ui-border)] text-sm font-semibold">
                                    <button type="button" x-on:click="cfg.categories.splice(ci, 1); send()"
                                            class="p-1.5 rounded text-[var(--ui-muted)] hover:text-red-600" title="Kategorie entfernen">
                                        @svg('heroicon-o-trash', 'w-4 h-4')
                                    </button>
                                </div>

                                <template x-for="(it, ii) in cat.items" :key="ii">
                                    <div class="flex items-start gap-2">
                                        <div class="flex-1 grid grid-cols-1 sm:grid-cols-[1fr_1fr_auto] gap-2">
                                            <input type="text" x-model="it.name" placeholder="Bezeichnung"
                                                   class="px-2 py-1.5 rounded border border-[var(--ui-border)] text-sm">
                                            <input type="text" x-model="it.description" placeholder="Beschreibung (optional)"
                                                   class="px-2 py-1.5 rounded border border-[var(--ui-border)] text-sm">
                                            <input type="text" x-model="it.price" placeholder="Preis"
                                                   class="w-24 px-2 py-1.5 rounded border border-[var(--ui-border)] text-sm">
                                        </div>
                                        <button type="button" x-on:click="cat.items.splice(ii, 1); send()"
                                                class="mt-1 p-1.5 rounded text-[var(--ui-muted)] hover:text-red-600" title="Eintrag entfernen">
                                            @svg('heroicon-o-x-mark', 'w-4 h-4')
                                        </button>
                                    </div>
                                </template>

                                <button type="button" x-on:click="cat.items.push({ name: '', description: '', price: '' }); send()"
                                        class="text-xs text-[var(--ui-primary)] hover:underline inline-flex items-center gap-1">
                                    @svg('heroicon-o-plus', 'w-3.5 h-3.5') Eintrag hinzufügen
                                </button>
                            </div>
                        </template>

                        <button type="button" x-on:click="cfg.categories.push({ name: '', items: [] }); send()"
                                class="inline-flex items-center gap-2 rounded-lg border border-[rgb(var(--ui-primary-rgb))]/40 bg-[rgb(var(--ui-primary-rgb))]/5 px-3 py-1.5 text-sm font-medium text-[rgb(var(--ui-primary-rgb))] hover:bg-[rgb(var(--ui-primary-rgb))]/10">
                            @svg('heroicon-o-plus', 'w-4 h-4') Kategorie hinzufügen
                        </button>
                    </div>

                    {{-- Tagesempfehlung --}}
                    <div class="pt-2 border-t border-[var(--ui-border)]/40">
                        <h3 class="text-sm font-bold text-[var(--ui-primary)] mb-2">Tagesempfehlung (optional)</h3>
                        <div class="grid grid-cols-1 sm:grid-cols-[1fr_1fr_auto] gap-2">
                            <input type="text" x-model="cfg.special.name" placeholder="Bezeichnung"
                                   class="px-2 py-1.5 rounded border border-[var(--ui-border)] text-sm">
                            <input type="text" x-model="cfg.special.description" placeholder="Beschreibung"
                                   class="px-2 py-1.5 rounded border border-[var(--ui-border)] text-sm">
                            <input type="text" x-model="cfg.special.price" placeholder="Preis"
                                   class="w-24 px-2 py-1.5 rounded border border-[var(--ui-border)] text-sm">
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
