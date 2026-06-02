<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="Medien" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'Digital Signage', 'href' => route('signage.dashboard'), 'icon' => 'tv'],
            ['label' => 'Medien'],
        ]">
            {{-- Label-as-button: ein verschachteltes <button> würde den Datei-Dialog blockieren. --}}
            <label class="inline-flex items-center justify-center gap-2 cursor-pointer select-none whitespace-nowrap font-medium transition-all duration-150 active:scale-[0.98] bg-[rgb(var(--ui-primary-rgb))] text-[color:var(--ui-on-primary)] border border-transparent shadow-sm hover:brightness-110 hover:shadow-md rounded-full px-2.5 py-1 text-sm">
                @svg('heroicon-o-arrow-up-tray', 'w-4 h-4')
                <span>Hochladen</span>
                <input type="file" class="hidden" wire:model="uploads" multiple
                       accept="image/*,video/*,audio/*,application/pdf,.ppt,.pptx">
            </label>
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
                            Eingebettete Player (iframe) starten oft erst nach einer Interaktion.
                        </p>
                        <x-ui-button type="submit" variant="primary">Stream hinzufügen</x-ui-button>
                    </div>
                </form>
            </x-ui-panel>

            <x-ui-panel title="Bibliothek" subtitle="Bilder, Videos, Audio, Streams, PDF und PowerPoint">
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
                                </div>
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
