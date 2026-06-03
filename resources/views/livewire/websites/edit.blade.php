<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar :title="$mediaId ? 'Website bearbeiten' : 'Website hinzufügen'" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'Digital Signage', 'href' => route('signage.dashboard'), 'icon' => 'tv'],
            ['label' => 'Medien', 'href' => route('signage.media.index')],
            ['label' => $mediaId ? 'Website bearbeiten' : 'Website hinzufügen'],
        ]" />
    </x-slot>

    <x-ui-page-container>
        <div class="max-w-2xl mx-auto space-y-6">
            <x-signage-panel title="Website" subtitle="Wird im Vollbild angezeigt (für die in der Wiedergabeliste eingestellte Dauer)" icon="globe-alt">
                <form wire:submit="save" class="space-y-5 p-5">
                    <div>
                        <x-ui-input-text name="name" label="Name" wire:model="name" placeholder="z.B. Speisekarte Web" />
                        @error('name')<span class="text-xs text-red-600">{{ $message }}</span>@enderror
                    </div>
                    <div>
                        <x-ui-input-text name="url" label="URL" wire:model="url" placeholder="https://…" />
                        @error('url')<span class="text-xs text-red-600">{{ $message }}</span>@enderror
                    </div>
                    <p class="text-xs text-[var(--ui-muted)]">
                        Hinweis: Manche Seiten verbieten die Einbettung (X-Frame-Options/CSP) und bleiben dann leer.
                        Eigene Seiten funktionieren am zuverlässigsten.
                    </p>
                    <div class="flex items-center gap-2 pt-1">
                        <x-ui-button type="submit" variant="primary">Speichern</x-ui-button>
                        <x-ui-button variant="secondary" :href="route('signage.media.index')">Abbrechen</x-ui-button>
                    </div>
                </form>
            </x-signage-panel>
        </div>
    </x-ui-page-container>
</x-ui-page>
