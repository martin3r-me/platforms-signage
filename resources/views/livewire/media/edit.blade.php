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
        </div>
    </x-ui-page-container>
</x-ui-page>
