@php
    $visualOptions = $visualPlaylists->map(fn($p) => ['value' => $p->id, 'label' => $p->name])->values()->all();
@endphp
<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar :title="$screen->name" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'Digital Signage', 'href' => route('signage.dashboard'), 'icon' => 'tv'],
            ['label' => 'Bildschirme', 'href' => route('signage.screens.index')],
            ['label' => $screen->name],
        ]">
            <x-ui-button size="sm" variant="secondary" wire:click="reload">
                @svg('heroicon-o-arrow-path', 'w-4 h-4')
                Neuladen
            </x-ui-button>
            <x-ui-button size="sm" variant="secondary" :href="$previewUrl" target="_blank">
                @svg('heroicon-o-arrow-top-right-on-square', 'w-4 h-4')
                Vollbild öffnen
            </x-ui-button>
        </x-ui-page-actionbar>
    </x-slot>

    <x-ui-page-container>
        <div class="space-y-6">
            @if(session('signage_message'))
                <div class="p-3 rounded bg-green-100 text-green-800 text-sm">{{ session('signage_message') }}</div>
            @endif

            <x-ui-panel title="Live-Vorschau">
                <div class="p-4">
                    <div class="relative w-full max-w-2xl mx-auto rounded-lg overflow-hidden border border-[var(--ui-border)]/40 bg-black {{ str_starts_with($screen->orientation, 'portrait') ? 'aspect-[9/16] max-w-xs' : 'aspect-video' }}">
                        <iframe src="{{ $previewUrl }}" class="absolute inset-0 w-full h-full" frameborder="0"></iframe>
                    </div>
                </div>
            </x-ui-panel>

            <x-ui-panel title="Einstellungen">
                <form wire:submit="saveSettings" class="space-y-4 p-4">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <x-ui-input-text name="name" label="Name" wire:model="name" />
                        <x-ui-input-select name="orientation" label="Ausrichtung" wire:model="orientation"
                            :options="[
                                ['value' => 'landscape', 'label' => 'Querformat'],
                                ['value' => 'landscape_180', 'label' => 'Querformat (180° gedreht)'],
                                ['value' => 'portrait', 'label' => 'Hochformat'],
                                ['value' => 'portrait_180', 'label' => 'Hochformat (180° gedreht)'],
                            ]"
                            optionValue="value" optionLabel="label" />
                        <x-ui-input-select name="defaultPlaylistId" label="Standard-Wiedergabeliste" wire:model="defaultPlaylistId"
                            :options="$visualOptions" optionValue="value" optionLabel="label" :nullable="true" nullLabel="– keine –" />
                        <x-ui-input-select name="scheduleId" label="Zeitplan" wire:model="scheduleId"
                            :options="$scheduleOptions" optionValue="value" optionLabel="label" :nullable="true" nullLabel="– kein Zeitplan –" />
                        <x-ui-input-select name="musicSource" label="Hintergrundmusik" wire:model="musicSource"
                            :options="$musicOptions" optionValue="value" optionLabel="label" :nullable="true" nullLabel="– keine –" />
                    </div>
                    <p class="text-xs text-[var(--ui-muted)]">
                        Der Zeitplan übersteuert die Standard-Wiedergabeliste in seinen Zeitfenstern. Zeitpläne werden unter
                        <a href="{{ route('signage.schedules.index') }}" wire:navigate class="text-[var(--ui-primary)] underline">Zeitpläne</a> verwaltet.
                    </p>
                    <x-ui-button type="submit" variant="primary">Speichern</x-ui-button>
                </form>
            </x-ui-panel>

        </div>
    </x-ui-page-container>
</x-ui-page>
