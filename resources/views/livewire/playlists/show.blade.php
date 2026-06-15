<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar :title="$playlist->name" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'Digital Signage', 'href' => route('signage.dashboard'), 'icon' => 'tv'],
            ['label' => 'Wiedergabelisten', 'href' => route('signage.playlists.index')],
            ['label' => $playlist->name],
        ]" />
    </x-slot>

    <x-ui-page-container>
        <div class="space-y-6 pt-4">
            @if(session('signage_message'))
                <div class="p-3 rounded bg-green-100 text-green-800 text-sm">{{ session('signage_message') }}</div>
            @endif

            <x-signage-panel color="violet" icon="queue-list" title="Wiedergabeliste" subtitle="Name & Beschreibung">
                <form wire:submit="rename" class="flex flex-col sm:flex-row items-start sm:items-end gap-3 p-4">
                    <div class="flex-1 w-full">
                        <x-ui-input-text name="name" label="Name" wire:model="name" />
                        @error('name')<span class="text-xs text-red-600">{{ $message }}</span>@enderror
                    </div>
                    <div class="flex-1 w-full">
                        <x-ui-input-text name="description" label="Beschreibung (optional)" wire:model="description" />
                    </div>
                    @if($playlist->kind !== 'music')
                        <div class="w-full sm:w-64">
                            <x-ui-input-select name="fit" label="Anzeige" wire:model="fit"
                                :options="[
                                    ['value' => 'contain', 'label' => 'Originalformat (mit Rand)'],
                                    ['value' => 'cover', 'label' => 'Vollbild (beschnitten)'],
                                ]"
                                optionValue="value" optionLabel="label" />
                        </div>
                    @endif
                    <x-ui-button type="submit" variant="primary">Speichern</x-ui-button>
                </form>
            </x-signage-panel>

            <x-signage-panel color="violet" icon="plus-circle" title="Element hinzufügen" subtitle="{{ $playlist->kind === 'music' ? 'Audiodateien' : 'Bilder, Videos, Dokumente, Apps, Websites' }}">
                <div class="p-4 space-y-3">
                    {{-- Direkt hochladen: Datei wird angelegt (taucht auch in den Medien auf) und sofort hinzugefügt --}}
                    <div class="flex flex-wrap items-center gap-2 rounded-lg border border-dashed border-[var(--ui-border)] px-3 py-2.5">
                        <label class="inline-flex items-center gap-2 cursor-pointer select-none font-medium text-sm rounded-full px-3 py-1.5 bg-[rgb(var(--ui-primary-rgb))] text-[color:var(--ui-on-primary)] hover:brightness-110 transition">
                            @svg('heroicon-o-arrow-up-tray', 'w-4 h-4')
                            <span>Datei hochladen</span>
                            <input type="file" class="hidden" wire:model="uploads" multiple
                                   accept="{{ $playlist->kind === 'music' ? 'audio/*' : 'image/*,video/*,application/pdf,.ppt,.pptx' }}">
                        </label>
                        <span class="text-[11px] text-[var(--ui-muted)]">Wird angelegt, landet auch in den <a href="{{ route('signage.media.index') }}" wire:navigate class="underline">Medien</a> und kommt ans Ende der Liste.</span>
                        <div wire:loading wire:target="uploads" class="w-full text-sm text-blue-700">Wird hochgeladen …</div>
                    </div>
                    @error('uploads.*')<p class="text-xs text-red-600">{{ $message }}</p>@enderror

                    {{-- Suche --}}
                    <div class="relative">
                        <span class="absolute left-3 top-1/2 -translate-y-1/2 text-[var(--ui-muted)]">
                            @svg('heroicon-o-magnifying-glass', 'w-4 h-4')
                        </span>
                        <input type="text" wire:model.live.debounce.300ms="mediaSearch"
                               placeholder="Medien durchsuchen …"
                               class="w-full pl-9 pr-3 py-2 text-sm rounded-lg border border-[var(--ui-border)]">
                    </div>

                    @if($available->isEmpty())
                        <div class="py-6 text-center text-sm text-[var(--ui-muted)]">
                            @if(trim($mediaSearch) !== '')
                                Keine Treffer für „{{ $mediaSearch }}".
                            @else
                                Keine passenden Medien. <a href="{{ route('signage.media.index') }}" wire:navigate class="text-[var(--ui-primary)] underline">Zuerst hochladen</a>.
                            @endif
                        </div>
                    @else
                        {{-- Vorschau-Grid: Klick fügt direkt hinzu --}}
                        <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 gap-3 max-h-80 overflow-y-auto pr-1">
                            @foreach($available as $m)
                                <button type="button" wire:click="addItem({{ $m->id }})" wire:key="pick-{{ $m->id }}"
                                        class="group text-left rounded-lg border border-[var(--ui-border)]/50 overflow-hidden bg-[var(--ui-muted-5)] hover:border-[rgb(var(--ui-primary-rgb))] hover:shadow-md transition">
                                    <div class="w-full flex items-center justify-center bg-black/5 relative overflow-hidden" style="aspect-ratio: 16 / 9">
                                        @php($preview = $m->previewUrl())
                                        @if($preview)
                                            <img src="{{ $preview }}" alt="{{ $m->name }}" class="w-full h-full object-cover" loading="lazy">
                                        @elseif($m->kind === 'video')
                                            @svg('heroicon-o-film', 'w-7 h-7 text-[var(--ui-muted)]')
                                        @elseif($m->kind === 'audio')
                                            @svg('heroicon-o-musical-note', 'w-7 h-7 text-[var(--ui-muted)]')
                                        @elseif($m->isApp())
                                            <iframe src="{{ route('signage.apps.preview', $m) }}" class="w-full h-full border-0 pointer-events-none" scrolling="no" loading="lazy" tabindex="-1"></iframe>
                                        @elseif($m->isWebsite())
                                            @svg('heroicon-o-globe-alt', 'w-7 h-7 text-[var(--ui-muted)]')
                                        @else
                                            @svg('heroicon-o-document', 'w-7 h-7 text-[var(--ui-muted)]')
                                        @endif
                                        <span class="absolute inset-0 bg-[rgb(var(--ui-primary-rgb))]/0 group-hover:bg-[rgb(var(--ui-primary-rgb))]/15 flex items-center justify-center opacity-0 group-hover:opacity-100 transition">
                                            @svg('heroicon-o-plus-circle', 'w-7 h-7 text-white drop-shadow')
                                        </span>
                                    </div>
                                    <div class="p-1.5">
                                        <div class="text-xs font-medium text-[var(--ui-secondary)] truncate" title="{{ $m->name }}">{{ $m->name }}</div>
                                        <div class="text-[10px] text-[var(--ui-muted)]">{{ $m->kindLabel() }}@if($m->kind === 'document' && $m->page_count) · {{ $m->page_count }} Seiten @endif</div>
                                    </div>
                                </button>
                            @endforeach
                        </div>
                        <p class="text-[11px] text-[var(--ui-muted)]">Auf ein Medium klicken, um es ans Ende der Liste zu setzen (max. 60 Treffer – ggf. Suche verfeinern).</p>
                    @endif
                </div>
            </x-signage-panel>

            <x-signage-panel color="violet" icon="bars-3" title="Reihenfolge" subtitle="Wird von oben nach unten abgespielt">
                @if($items->isEmpty())
                    <div class="p-8 text-center text-[var(--ui-muted)]">Noch keine Elemente.</div>
                @else
                    <div class="divide-y divide-[var(--ui-border)]/40">
                        @foreach($items as $item)
                            <div class="flex items-center gap-3 p-3" wire:key="item-{{ $item->id }}">
                                <div class="flex flex-col">
                                    <button wire:click="move({{ $item->id }}, 'up')" @disabled($loop->first) class="text-[var(--ui-muted)] hover:text-[var(--ui-secondary)] disabled:opacity-30">
                                        @svg('heroicon-o-chevron-up', 'w-4 h-4')
                                    </button>
                                    <button wire:click="move({{ $item->id }}, 'down')" @disabled($loop->last) class="text-[var(--ui-muted)] hover:text-[var(--ui-secondary)] disabled:opacity-30">
                                        @svg('heroicon-o-chevron-down', 'w-4 h-4')
                                    </button>
                                </div>
                                <div class="w-16 h-10 rounded bg-black/5 flex items-center justify-center overflow-hidden shrink-0">
                                    @php($preview = $item->media?->previewUrl())
                                    @if($preview)
                                        <img src="{{ $preview }}" class="w-full h-full object-cover">
                                    @elseif($item->media?->kind === 'video')
                                        @svg('heroicon-o-film', 'w-5 h-5 text-[var(--ui-muted)]')
                                    @elseif($item->media?->kind === 'audio')
                                        @svg('heroicon-o-musical-note', 'w-5 h-5 text-[var(--ui-muted)]')
                                    @elseif($item->media?->kind === 'app')
                                        <iframe src="{{ route('signage.apps.preview', $item->media) }}" class="w-full h-full border-0 pointer-events-none" scrolling="no" loading="lazy" tabindex="-1"></iframe>
                                    @elseif($item->media?->kind === 'website')
                                        @svg('heroicon-o-globe-alt', 'w-5 h-5 text-[var(--ui-muted)]')
                                    @else
                                        @svg('heroicon-o-document', 'w-5 h-5 text-[var(--ui-muted)]')
                                    @endif
                                </div>
                                <div class="flex-1 min-w-0">
                                    <div class="font-medium text-[var(--ui-secondary)] truncate">{{ $item->media?->name ?? 'Gelöscht' }}</div>
                                    <div class="text-xs text-[var(--ui-muted)]">
                                        {{ $item->media?->kindLabel() }}
                                        @if($item->media?->kind === 'document' && $item->media?->page_count)
                                            · {{ $item->media->page_count }} Seiten
                                        @endif
                                    </div>
                                </div>
                                @if($playlist->kind !== 'music' && in_array($item->media?->kind, ['image', 'document', 'app', 'website']))
                                    <div class="flex items-center gap-1">
                                        <input type="number" min="1" value="{{ $item->duration_seconds }}"
                                               placeholder="{{ $defaultDuration }}"
                                               wire:change="updateDuration({{ $item->id }}, $event.target.value)"
                                               class="w-20 px-2 py-1 text-sm rounded border border-[var(--ui-border)]">
                                        <span class="text-xs text-[var(--ui-muted)]">Sek.</span>
                                    </div>
                                @endif
                                <button wire:click="removeItem({{ $item->id }})" class="p-1.5 rounded text-[var(--ui-muted)] hover:text-red-600">
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
