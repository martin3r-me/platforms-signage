<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="Uhr-App" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'Digital Signage', 'href' => route('signage.dashboard'), 'icon' => 'tv'],
            ['label' => 'Medien', 'href' => route('signage.media.index')],
            ['label' => $mediaId ? 'Uhr bearbeiten' : 'Uhr hinzufügen'],
        ]" />
    </x-slot>

    <x-ui-page-container>
        <div class="max-w-2xl mx-auto space-y-6">
            <x-ui-panel title="Uhr" subtitle="Zeigt die aktuelle Uhrzeit (Geräte-Zeitzone)">
                <form wire:submit="save" class="space-y-5 p-4">
                    <div>
                        <x-ui-input-text name="name" label="Name" wire:model="name" placeholder="z.B. Empfang Uhr" />
                        @error('name')<span class="text-xs text-red-600">{{ $message }}</span>@enderror
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <x-ui-input-select name="clockType" label="Uhr-Typ" wire:model="clockType"
                            :options="[
                                ['value' => 'modern_digital', 'label' => 'Modern Digital'],
                                ['value' => 'minimal', 'label' => 'Minimalistisch'],
                                ['value' => 'flip', 'label' => 'Flip-Clock (animiert)'],
                            ]"
                            optionValue="value" optionLabel="label" />

                        <x-ui-input-select name="theme" label="Thema" wire:model="theme"
                            :options="[
                                ['value' => 'dark', 'label' => 'Dunkel'],
                                ['value' => 'light', 'label' => 'Hell'],
                            ]"
                            optionValue="value" optionLabel="label" />
                    </div>

                    <div class="pt-2 border-t border-[var(--ui-border)]/40">
                        <h3 class="text-sm font-bold text-[var(--ui-primary)] mb-3">Einstellungen</h3>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <x-ui-input-select name="timeFormat" label="Zeitformat" wire:model="timeFormat"
                                :options="[
                                    ['value' => '24h', 'label' => '24-Stunden (13:30)'],
                                    ['value' => '12h', 'label' => '12-Stunden (1:30 PM)'],
                                ]"
                                optionValue="value" optionLabel="label" />

                            <x-ui-input-select name="dateFormat" label="Datumsformat" wire:model="dateFormat"
                                :options="[
                                    ['value' => 'de_long', 'label' => '1. Januar 2024'],
                                    ['value' => 'de_short', 'label' => '01.01.2024'],
                                    ['value' => 'en_long', 'label' => 'January 01, 2024'],
                                    ['value' => 'iso', 'label' => '2024-01-01'],
                                ]"
                                optionValue="value" optionLabel="label" />
                        </div>

                        <div class="flex flex-col gap-2 mt-4">
                            <label class="inline-flex items-center gap-2 cursor-pointer text-sm">
                                <input type="checkbox" wire:model="showSeconds" class="rounded">
                                Sekunden anzeigen
                            </label>
                            <label class="inline-flex items-center gap-2 cursor-pointer text-sm">
                                <input type="checkbox" wire:model="showDate" class="rounded">
                                Datum anzeigen
                            </label>
                        </div>
                    </div>

                    <div class="flex items-center gap-2 pt-2">
                        <x-ui-button type="submit" variant="primary">Speichern</x-ui-button>
                        <x-ui-button variant="secondary" :href="route('signage.media.index')">Abbrechen</x-ui-button>
                    </div>
                </form>
            </x-ui-panel>
        </div>
    </x-ui-page-container>
</x-ui-page>
