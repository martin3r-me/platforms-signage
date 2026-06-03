<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="Medium bearbeiten" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'Digital Signage', 'href' => route('signage.dashboard'), 'icon' => 'tv'],
            ['label' => 'Medien', 'href' => route('signage.media.index')],
            ['label' => 'Bearbeiten'],
        ]" />
    </x-slot>

    <x-ui-page-container>
        <div class="max-w-2xl mx-auto space-y-6 pt-4">
            @if(session('signage_message'))
                <div class="p-3 rounded bg-green-100 text-green-800 text-sm">{{ session('signage_message') }}</div>
            @endif

            <x-signage-panel title="Medium" subtitle="{{ $media->kind }}" icon="photo">
                <form wire:submit="save" class="space-y-5 p-5">
                    <div class="flex items-start gap-4">
                        <div class="w-40 shrink-0 aspect-video rounded-lg overflow-hidden border border-[var(--ui-border)]/40 bg-black/5 flex items-center justify-center">
                            @if($preview)
                                <img src="{{ $preview }}" alt="{{ $media->name }}" class="w-full h-full object-cover">
                            @elseif($media->kind === 'video')
                                @svg('heroicon-o-film', 'w-10 h-10 text-[var(--ui-muted)]')
                            @elseif($media->kind === 'audio')
                                @svg('heroicon-o-musical-note', 'w-10 h-10 text-[var(--ui-muted)]')
                            @else
                                @svg('heroicon-o-document', 'w-10 h-10 text-[var(--ui-muted)]')
                            @endif
                        </div>
                        <div class="flex-1 space-y-4">
                            <div>
                                <x-ui-input-text name="name" label="Name" wire:model="name" />
                                @error('name')<span class="text-xs text-red-600">{{ $message }}</span>@enderror
                            </div>
                            <x-ui-input-select name="folderId" label="Ordner" wire:model="folderId"
                                :options="$folderOptions" optionValue="value" optionLabel="label" :nullable="true" nullLabel="– kein Ordner –" />
                        </div>
                    </div>

                    <div class="flex items-center gap-2 pt-1">
                        <x-ui-button type="submit" variant="primary">Speichern</x-ui-button>
                        <x-ui-button variant="secondary" :href="route('signage.media.index')">Abbrechen</x-ui-button>
                    </div>
                </form>
            </x-signage-panel>

            @if($media->isDocument())
                <x-signage-panel icon="document-duplicate" title="Seiten"
                    subtitle="Jede Seite wird in der Wiedergabeliste einzeln für die eingestellte Dauer angezeigt">
                    @if($media->processing_status !== 'ready')
                        <div class="p-6 text-center text-sm text-[var(--ui-muted)]">
                            Das Dokument wird noch verarbeitet. Die Seiten erscheinen, sobald die Umwandlung fertig ist.
                        </div>
                    @elseif($pages->isEmpty())
                        <div class="p-6 text-center text-sm text-[var(--ui-muted)]">Keine Seiten vorhanden.</div>
                    @else
                        <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-3 p-4">
                            @foreach($pages as $page)
                                <div class="rounded-lg border border-[var(--ui-border)]/50 overflow-hidden bg-[var(--ui-muted-5)] flex flex-col" wire:key="page-{{ $page->id }}">
                                    <div class="aspect-[3/4] bg-black/5 flex items-center justify-center overflow-hidden">
                                        @php($pageUrl = $page->previewUrl())
                                        @if($pageUrl)
                                            <img src="{{ $pageUrl }}" alt="Seite {{ $page->page_number }}" class="w-full h-full object-contain" loading="lazy">
                                        @else
                                            @svg('heroicon-o-document', 'w-8 h-8 text-[var(--ui-muted)]')
                                        @endif
                                    </div>
                                    <div class="flex items-center justify-between gap-1 px-2 py-1.5">
                                        <span class="text-xs font-medium text-[var(--ui-secondary)]">Seite {{ $page->page_number }}</span>
                                        <div class="flex items-center gap-0.5">
                                            <button type="button" wire:click="movePage({{ $page->id }}, 'up')"
                                                    @disabled($loop->first)
                                                    class="p-1 rounded text-[var(--ui-muted)] hover:text-[var(--ui-secondary)] disabled:opacity-30" title="Nach vorne">
                                                @svg('heroicon-o-chevron-up', 'w-4 h-4')
                                            </button>
                                            <button type="button" wire:click="movePage({{ $page->id }}, 'down')"
                                                    @disabled($loop->last)
                                                    class="p-1 rounded text-[var(--ui-muted)] hover:text-[var(--ui-secondary)] disabled:opacity-30" title="Nach hinten">
                                                @svg('heroicon-o-chevron-down', 'w-4 h-4')
                                            </button>
                                            <button type="button" wire:click="removePage({{ $page->id }})"
                                                    wire:confirm="Diese Seite wirklich entfernen?"
                                                    class="p-1 rounded text-[var(--ui-muted)] hover:text-red-600" title="Seite entfernen">
                                                @svg('heroicon-o-trash', 'w-4 h-4')
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </x-signage-panel>
            @endif
        </div>
    </x-ui-page-container>
</x-ui-page>
