<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar :title="$group->name" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'Digital Signage', 'href' => route('signage.dashboard'), 'icon' => 'tv'],
            ['label' => 'Gruppen', 'href' => route('signage.groups.index')],
            ['label' => $group->name],
        ]" />
    </x-slot>

    <x-ui-page-container>
        <div class="space-y-6 pt-4">
            @if(session('signage_message'))
                <div class="p-3 rounded bg-green-100 text-green-800 text-sm">{{ session('signage_message') }}</div>
            @endif

            <x-signage-panel icon="rectangle-group" title="Gruppe" subtitle="Name & Mitglieder">
                <form wire:submit="saveGroup" class="space-y-4 p-4">
                    <div>
                        <x-ui-input-text name="name" label="Name" wire:model="name" />
                        @error('name')<span class="text-xs text-red-600">{{ $message }}</span>@enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-[var(--ui-secondary)] mb-1">Mitglieder (Bildschirme)</label>
                        @if($screens->isEmpty())
                            <p class="text-xs text-[var(--ui-muted)]">Keine gekoppelten Bildschirme im Team.</p>
                        @else
                            <div class="space-y-1.5 rounded-lg border border-[var(--ui-border)] p-3 max-h-64 overflow-y-auto">
                                @foreach($screens as $screen)
                                    <label class="flex items-center gap-2 text-sm cursor-pointer">
                                        <input type="checkbox" value="{{ $screen->id }}" wire:model="memberIds" class="rounded">
                                        <span class="text-[var(--ui-secondary)]">{{ $screen->name }}</span>
                                    </label>
                                @endforeach
                            </div>
                        @endif
                    </div>

                    <x-ui-button type="submit" variant="primary">Speichern</x-ui-button>
                </form>
            </x-signage-panel>

            <x-signage-panel color="amber" icon="bolt" title="Auf Gruppe anwenden" subtitle="Einstellungen auf alle Mitglieder schreiben">
                <form wire:submit="applyToGroup" class="space-y-4 p-4">
                    <p class="text-xs text-[var(--ui-muted)]">
                        Überschreibt bei <strong>allen</strong> Mitglieds-Bildschirmen die gewählten Felder und lädt sie neu.
                    </p>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <x-ui-input-select name="applyDefaultPlaylistId" label="Standard-Wiedergabeliste" wire:model="applyDefaultPlaylistId"
                            :options="$playlistOptions" optionValue="value" optionLabel="label" :nullable="true" nullLabel="– nicht ändern –" />
                        <div>
                            <label class="block text-sm font-medium text-[var(--ui-secondary)] mb-1">Zeitpläne (ersetzt die der Mitglieder)</label>
                            @if(count($scheduleOptions))
                                <div class="space-y-1.5 rounded-lg border border-[var(--ui-border)] p-3 max-h-40 overflow-y-auto">
                                    @foreach($scheduleOptions as $opt)
                                        <label class="flex items-center gap-2 text-sm cursor-pointer">
                                            <input type="checkbox" value="{{ $opt['value'] }}" wire:model="applyScheduleIds" class="rounded">
                                            <span class="text-[var(--ui-secondary)]">{{ $opt['label'] }}</span>
                                        </label>
                                    @endforeach
                                </div>
                            @else
                                <p class="text-xs text-[var(--ui-muted)]">Keine Zeitpläne angelegt.</p>
                            @endif
                        </div>
                    </div>
                    <x-ui-button type="submit" variant="primary"
                                 wire:confirm="Einstellungen auf alle Bildschirme dieser Gruppe anwenden?">
                        Anwenden
                    </x-ui-button>
                </form>
            </x-signage-panel>
        </div>
    </x-ui-page-container>
</x-ui-page>
