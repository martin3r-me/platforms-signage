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

            <x-ui-panel title="Stream einbinden" subtitle="Internet-Radio / Audio-Stream als Hintergrundmusik">
                <form wire:submit="addStream" class="space-y-3 p-4">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <x-ui-input-text name="streamName" label="Name" wire:model="streamName" placeholder="z.B. Lounge Radio" />
                            @error('streamName')<span class="text-xs text-red-600">{{ $message }}</span>@enderror
                        </div>
                        <div>
                            <x-ui-input-select name="streamType" label="Typ" wire:model="streamType"
                                :options="[
                                    ['value' => 'stream', 'label' => 'Direkter Stream (empfohlen, autoplay)'],
                                    ['value' => 'embed', 'label' => 'Eingebetteter Player / iframe (z.B. TuneIn)'],
                                ]"
                                optionValue="value" optionLabel="label" />
                        </div>
                    </div>
                    <div>
                        <x-ui-input-text name="streamUrl" label="URL" wire:model="streamUrl"
                            placeholder="https://… (Stream-URL oder https://tunein.com/embed/player/s309907/)" />
                        @error('streamUrl')<span class="text-xs text-red-600">{{ $message }}</span>@enderror
                    </div>
                    <div class="flex items-center justify-between">
                        <p class="text-xs text-[var(--ui-muted)]">
                            Direkte Stream-URLs (z.B. Icecast/SHOUTcast <code>.mp3</code>/<code>.aac</code>) starten auf TVs zuverlässig automatisch.
                            <strong>TuneIn-Links werden automatisch in einen direkten Stream aufgelöst.</strong>
                            Reine Embed-Player (iframe) starten dagegen oft erst nach einer Interaktion.
                        </p>
                        <x-ui-button type="submit" variant="primary">Stream hinzufügen</x-ui-button>
                    </div>
                </form>
            </x-ui-panel>

            <x-ui-panel title="Ordner" subtitle="Medien sauber organisieren">
                <div class="flex flex-wrap items-center gap-2 p-4">
                    <button wire:click="openFolder(null)"
                            class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-sm border {{ $currentFolderId === null ? 'bg-[rgb(var(--ui-primary-rgb))] text-[color:var(--ui-on-primary)] border-transparent' : 'border-[var(--ui-border)] text-[var(--ui-secondary)] hover:bg-[var(--ui-muted-5)]' }}">
                        @svg('heroicon-o-squares-2x2', 'w-4 h-4') Alle
                    </button>
                    @foreach($folders as $f)
                        <button wire:click="openFolder({{ $f->id }})" wire:key="folder-{{ $f->id }}"
                                class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-sm border {{ $currentFolderId === $f->id ? 'bg-[rgb(var(--ui-primary-rgb))] text-[color:var(--ui-on-primary)] border-transparent' : 'border-[var(--ui-border)] text-[var(--ui-secondary)] hover:bg-[var(--ui-muted-5)]' }}">
                            @svg('heroicon-o-folder', 'w-4 h-4') {{ $f->name }} <span class="opacity-70">({{ $f->media_count }})</span>
                        </button>
                    @endforeach

                    <form wire:submit="createFolder" class="inline-flex items-center gap-1 ml-auto">
                        <input type="text" wire:model="newFolderName" placeholder="Neuer Ordner"
                               class="px-3 py-1.5 text-sm rounded-full border border-[var(--ui-border)]">
                        <x-ui-button type="submit" size="sm" variant="secondary">
                            @svg('heroicon-o-folder-plus', 'w-4 h-4') Anlegen
                        </x-ui-button>
                    </form>
                </div>
                @error('newFolderName')<div class="px-4 pb-3 text-xs text-red-600">{{ $message }}</div>@enderror

                @if($currentFolder)
                    <div class="px-4 pb-4 flex items-center justify-between">
                        <span class="text-sm text-[var(--ui-muted)]">Ordner: <strong>{{ $currentFolder->name }}</strong> — neue Uploads landen hier.</span>
                        <button wire:click="deleteFolder({{ $currentFolder->id }})"
                                wire:confirm="Ordner löschen? Die Medien bleiben erhalten und landen unter Alle."
                                class="text-xs text-red-600 hover:underline inline-flex items-center gap-1">
                            @svg('heroicon-o-trash', 'w-3.5 h-3.5') Ordner löschen
                        </button>
                    </div>
                @endif
            </x-ui-panel>

            <x-ui-panel :title="$currentFolder ? 'Bibliothek · '.$currentFolder->name : 'Bibliothek'" subtitle="Bilder, Videos, Audio, Streams, PDF, PowerPoint und Apps">
                @if($media->isEmpty())
                    <div class="p-8 text-center text-[var(--ui-muted)]">Noch keine Medien hochgeladen.</div>
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
    </x-ui-page-container>
</x-ui-page>
