<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="Medien" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'Digital Signage', 'href' => route('signage.dashboard'), 'icon' => 'tv'],
            ['label' => 'Medien'],
        ]">
            <label class="inline-flex items-center gap-2 cursor-pointer">
                <x-ui-button variant="primary" size="sm" tag="span">
                    @svg('heroicon-o-arrow-up-tray', 'w-4 h-4')
                    Hochladen
                </x-ui-button>
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

            <x-ui-panel title="Bibliothek" subtitle="Bilder, Videos, Audio, PDF und PowerPoint">
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
                                    <div class="flex items-center justify-between mt-1">
                                        <span class="text-[10px] uppercase tracking-wide text-[var(--ui-muted)]">
                                            {{ $m->kind }}@if($m->kind === 'document' && $m->page_count) · {{ $m->page_count }} S.@endif
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
