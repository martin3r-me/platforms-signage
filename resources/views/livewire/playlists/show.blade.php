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
        <div class="space-y-6">
            <x-ui-panel title="Element hinzufügen" subtitle="{{ $playlist->kind === 'music' ? 'Audiodateien' : 'Bilder, Videos, Dokumente' }}">
                <div class="flex flex-col sm:flex-row items-start sm:items-end gap-3 p-4">
                    <div class="flex-1 w-full">
                        <x-ui-input-select name="addMediaId" label="Medium" wire:model="addMediaId"
                            :options="$available->map(fn($m) => ['value' => $m->id, 'label' => $m->name.' ('.$m->kind.')'])->values()->all()"
                            optionValue="value" optionLabel="label" :nullable="true" nullLabel="– auswählen –" />
                        @error('addMediaId')<span class="text-xs text-red-600">{{ $message }}</span>@enderror
                    </div>
                    <x-ui-button wire:click="addItem" variant="primary">Hinzufügen</x-ui-button>
                </div>
                @if($available->isEmpty())
                    <div class="px-4 pb-4 text-sm text-[var(--ui-muted)]">
                        Keine passenden Medien. <a href="{{ route('signage.media.index') }}" wire:navigate class="text-[var(--ui-primary)] underline">Zuerst hochladen</a>.
                    </div>
                @endif
            </x-ui-panel>

            <x-ui-panel title="Reihenfolge" subtitle="Wird von oben nach unten abgespielt">
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
                                    @else
                                        @svg('heroicon-o-document', 'w-5 h-5 text-[var(--ui-muted)]')
                                    @endif
                                </div>
                                <div class="flex-1 min-w-0">
                                    <div class="font-medium text-[var(--ui-secondary)] truncate">{{ $item->media?->name ?? 'Gelöscht' }}</div>
                                    <div class="text-xs text-[var(--ui-muted)]">
                                        {{ $item->media?->kind }}
                                        @if($item->media?->kind === 'document' && $item->media?->page_count)
                                            · {{ $item->media->page_count }} Seiten
                                        @endif
                                    </div>
                                </div>
                                @if($playlist->kind !== 'music' && in_array($item->media?->kind, ['image', 'document']))
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
            </x-ui-panel>
        </div>
    </x-ui-page-container>
</x-ui-page>
