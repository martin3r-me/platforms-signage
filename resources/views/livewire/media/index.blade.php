<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="Medien" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'Digital Signage', 'href' => route('signage.dashboard'), 'icon' => 'tv'],
            ['label' => 'Medien'],
        ]">
            <div class="flex items-center gap-2">
                <x-ui-button variant="secondary" size="sm" :href="route('signage.apps.clock.create')">
                    @svg('heroicon-o-clock', 'w-4 h-4')
                    Uhr-App
                </x-ui-button>
                <x-ui-button variant="secondary" size="sm" :href="route('signage.apps.weather.create')">
                    @svg('heroicon-o-cloud', 'w-4 h-4')
                    Wetter-App
                </x-ui-button>
                <x-ui-button variant="secondary" size="sm" :href="route('signage.streams.create')">
                    @svg('heroicon-o-signal', 'w-4 h-4')
                    Stream einbinden
                </x-ui-button>
                {{-- Label-as-button: ein verschachteltes <button> würde den Datei-Dialog blockieren. --}}
                <label class="inline-flex items-center justify-center gap-2 cursor-pointer select-none whitespace-nowrap font-medium transition-all duration-150 active:scale-[0.98] bg-[rgb(var(--ui-primary-rgb))] text-[color:var(--ui-on-primary)] border border-transparent shadow-sm hover:brightness-110 hover:shadow-md rounded-full px-2.5 py-1 text-sm">
                    @svg('heroicon-o-arrow-up-tray', 'w-4 h-4')
                    <span>Hochladen</span>
                    <input type="file" class="hidden" wire:model="uploads" multiple
                           accept="image/*,video/*,audio/*,application/pdf,.ppt,.pptx">
                </label>
            </div>
        </x-ui-page-actionbar>
    </x-slot>

    {{-- Ordner-Navigation (Seitenleiste) --}}
    <x-slot name="sidebar">
        <x-ui-page-sidebar title="Ordner" width="w-72" :defaultOpen="true">
            <div class="p-3 space-y-1">
                <button wire:click="openFolder(null)"
                        class="w-full flex items-center gap-2 px-3 py-2 rounded-lg text-sm {{ $currentFolderId === null ? 'bg-[rgb(var(--ui-primary-rgb))] text-[color:var(--ui-on-primary)]' : 'text-[var(--ui-secondary)] hover:bg-[var(--ui-muted-5)]' }}">
                    @svg('heroicon-o-squares-2x2', 'w-4 h-4')
                    Alle Medien
                </button>

                @foreach($folders as $f)
                    <div class="group flex items-center gap-1" wire:key="navfolder-{{ $f->id }}">
                        <button wire:click="openFolder({{ $f->id }})"
                                class="flex-1 min-w-0 flex items-center justify-between gap-2 px-3 py-2 rounded-lg text-sm {{ $currentFolderId === $f->id ? 'bg-[rgb(var(--ui-primary-rgb))] text-[color:var(--ui-on-primary)]' : 'text-[var(--ui-secondary)] hover:bg-[var(--ui-muted-5)]' }}">
                            <span class="flex items-center gap-2 min-w-0">
                                @svg('heroicon-o-folder', 'w-4 h-4 shrink-0')
                                <span class="truncate">{{ $f->name }}</span>
                            </span>
                            <span class="text-xs opacity-60 shrink-0">{{ $f->media_count }}</span>
                        </button>
                        <button wire:click="deleteFolder({{ $f->id }})"
                                wire:confirm="Ordner löschen? Die Medien bleiben erhalten und landen unter Alle Medien."
                                class="shrink-0 p-1 rounded text-[var(--ui-muted)] opacity-0 group-hover:opacity-100 hover:text-red-600"
                                title="Ordner löschen">
                            @svg('heroicon-o-trash', 'w-4 h-4')
                        </button>
                    </div>
                @endforeach
            </div>

            <div class="p-3 border-t border-[var(--ui-border)]/40">
                <form wire:submit="createFolder" class="flex items-center gap-2">
                    <input type="text" wire:model="newFolderName" placeholder="Neuer Ordner"
                           class="flex-1 min-w-0 px-3 py-1.5 text-sm rounded-lg border border-[var(--ui-border)]">
                    <x-ui-button type="submit" size="sm" variant="secondary" iconOnly>
                        @svg('heroicon-o-plus', 'w-4 h-4')
                    </x-ui-button>
                </form>
                @error('newFolderName')<span class="text-xs text-red-600">{{ $message }}</span>@enderror
            </div>
        </x-ui-page-sidebar>
    </x-slot>

    <x-ui-page-container>
        <div class="space-y-4" @if($hasProcessing) wire:poll.6s @endif>
            @if(session('signage_message'))
                <div class="p-3 rounded bg-green-100 text-green-800 text-sm">{{ session('signage_message') }}</div>
            @endif

            <div wire:loading wire:target="uploads" class="p-3 rounded bg-blue-50 text-blue-700 text-sm">
                Wird hochgeladen …
            </div>

            @error('uploads.*')
                <div class="p-3 rounded bg-red-100 text-red-800 text-sm">{{ $message }}</div>
            @enderror

            {{-- Drag & Drop-Zone über der Bibliothek --}}
            <div x-data="{
                    dragging: false,
                    drop(e) {
                        this.dragging = false;
                        const files = e.dataTransfer && e.dataTransfer.files;
                        if (!files || !files.length) return;
                        const input = this.$refs.dropInput;
                        input.files = files;
                        input.dispatchEvent(new Event('change', { bubbles: true }));
                    }
                 }"
                 x-on:dragenter.prevent="dragging = true"
                 x-on:dragover.prevent="dragging = true"
                 x-on:dragleave.prevent="dragging = false"
                 x-on:drop.prevent="drop($event)"
                 class="relative">

                <input type="file" class="hidden" multiple wire:model="uploads" x-ref="dropInput"
                       accept="image/*,video/*,audio/*,application/pdf,.ppt,.pptx">

                {{-- Overlay beim Ziehen --}}
                <div x-show="dragging" x-cloak
                     class="absolute inset-0 z-10 rounded-xl border-2 border-dashed border-[rgb(var(--ui-primary-rgb))] bg-[rgb(var(--ui-primary-rgb))]/10 flex items-center justify-center pointer-events-none">
                    <div class="text-[rgb(var(--ui-primary-rgb))] font-medium flex items-center gap-2">
                        @svg('heroicon-o-arrow-down-tray', 'w-6 h-6')
                        Dateien hier ablegen{{ $currentFolder ? ' · '.$currentFolder->name : '' }}
                    </div>
                </div>

                <x-ui-panel :title="$currentFolder ? 'Bibliothek · '.$currentFolder->name : 'Bibliothek'" subtitle="Ziehe Dateien hierher oder nutze „Hochladen" — Bilder, Videos, Audio, Streams, PDF, PowerPoint und Apps">
                    @if($media->isEmpty())
                        <div class="p-10 text-center text-[var(--ui-muted)]">
                            @svg('heroicon-o-arrow-down-tray', 'w-8 h-8 mx-auto mb-2 opacity-60')
                            Noch keine Medien{{ $currentFolder ? ' in diesem Ordner' : '' }}. Dateien hierher ziehen oder „Hochladen".
                        </div>
                    @else
                        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-6 gap-4 p-4">
                            @foreach($media as $m)
                                <div class="group relative rounded-lg overflow-hidden border border-[var(--ui-border)]/40 bg-[var(--ui-muted-5)]" wire:key="media-{{ $m->id }}">
                                    <div class="aspect-video flex items-center justify-center bg-black/5">
                                        @php($preview = $m->previewUrl())
                                        @if($preview)
                                            <img src="{{ $preview }}" alt="{{ $m->name }}" class="w-full h-full object-cover">
                                        @elseif($m->isStream())
                                            @svg('heroicon-o-signal', 'w-10 h-10 text-[var(--ui-muted)]')
                                        @elseif($m->isApp())
                                            <iframe src="{{ route('signage.apps.preview', $m) }}" class="w-full h-full border-0 pointer-events-none" scrolling="no" loading="lazy" tabindex="-1"></iframe>
                                        @elseif($m->kind === 'video')
                                            @svg('heroicon-o-film', 'w-10 h-10 text-[var(--ui-muted)]')
                                        @elseif($m->kind === 'audio')
                                            @svg('heroicon-o-musical-note', 'w-10 h-10 text-[var(--ui-muted)]')
                                        @else
                                            @svg('heroicon-o-document', 'w-10 h-10 text-[var(--ui-muted)]')
                                        @endif
                                    </div>
                                    <div class="p-2">
                                        <div class="text-xs font-medium text-[var(--ui-secondary)] truncate" title="{{ $m->name }}">{{ $m->name }}</div>
                                        @if($m->isStream())
                                            <div class="text-[10px] text-[var(--ui-muted)] truncate" title="{{ $m->stream_url }}">{{ $m->stream_url }}</div>
                                        @endif
                                        <div class="flex items-center justify-between mt-1">
                                            <span class="text-[10px] uppercase tracking-wide text-[var(--ui-muted)]">
                                                @if($m->isStream())
                                                    {{ $m->is_embed ? 'Embed' : 'Stream' }}
                                                @elseif($m->isApp())
                                                    App · {{ $m->app_type }}
                                                @else
                                                    {{ $m->kind }}
                                                    @if($m->kind === 'document' && $m->page_count) · {{ $m->page_count }} S.@endif
                                                @endif
                                            </span>
                                            @if($m->kind === 'document' && $m->processing_status !== 'ready')
                                                <span class="text-[10px] {{ $m->processing_status === 'failed' ? 'text-red-600' : 'text-blue-600' }}">
                                                    {{ $m->processing_status === 'failed' ? 'Fehler' : 'wird verarbeitet …' }}
                                                </span>
                                            @endif
                                        </div>
                                        @if($m->kind === 'document' && $m->processing_status !== 'ready')
                                            <button wire:click="reprocessDocument({{ $m->id }})"
                                                    class="mt-1 w-full text-[10px] px-1 py-1 rounded border border-[var(--ui-border)] text-[var(--ui-secondary)] hover:bg-[var(--ui-muted-5)] inline-flex items-center justify-center gap-1">
                                                @svg('heroicon-o-arrow-path', 'w-3 h-3') Erneut verarbeiten
                                            </button>
                                        @endif
                                        @if(count($folderOptions))
                                            <select wire:change="moveToFolder({{ $m->id }}, $event.target.value)"
                                                    class="mt-1.5 w-full text-[10px] px-1 py-1 rounded border border-[var(--ui-border)] bg-white text-[var(--ui-secondary)]">
                                                <option value="">📁 Ordner: —</option>
                                                @foreach($folders as $f)
                                                    <option value="{{ $f->id }}" @selected($m->folder_id === $f->id)>{{ $f->name }}</option>
                                                @endforeach
                                            </select>
                                        @endif
                                    </div>
                                    @if($m->isApp())
                                        <a href="{{ route('signage.apps.'.$m->app_type.'.edit', $m) }}" wire:navigate
                                           class="absolute top-1.5 left-1.5 p-1 rounded bg-black/50 text-white opacity-0 group-hover:opacity-100 transition">
                                            @svg('heroicon-o-pencil-square', 'w-4 h-4')
                                        </a>
                                    @elseif($m->isStream())
                                        <a href="{{ route('signage.streams.edit', $m) }}" wire:navigate
                                           class="absolute top-1.5 left-1.5 p-1 rounded bg-black/50 text-white opacity-0 group-hover:opacity-100 transition">
                                            @svg('heroicon-o-pencil-square', 'w-4 h-4')
                                        </a>
                                    @endif
                                    <button wire:click="deleteMedia({{ $m->id }})"
                                            wire:confirm="Dieses Medium wirklich löschen?"
                                            class="absolute top-1.5 right-1.5 p-1 rounded bg-black/50 text-white opacity-0 group-hover:opacity-100 transition">
                                        @svg('heroicon-o-trash', 'w-4 h-4')
                                    </button>
                                </div>
                            @endforeach
                        </div>
                        <div class="p-4">{{ $media->links() }}</div>
                    @endif
                </x-ui-panel>
            </div>
        </div>
    </x-ui-page-container>
</x-ui-page>
