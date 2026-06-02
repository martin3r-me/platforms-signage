@php
    $visualOptions = $visualPlaylists->map(fn($p) => ['value' => $p->id, 'label' => $p->name])->values()->all();
    $musicOptions = $musicPlaylists->map(fn($p) => ['value' => $p->id, 'label' => $p->name])->values()->all();
    $weekdays = [1 => 'Mo', 2 => 'Di', 3 => 'Mi', 4 => 'Do', 5 => 'Fr', 6 => 'Sa', 7 => 'So'];
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
                        <x-ui-input-select name="musicPlaylistId" label="Hintergrundmusik" wire:model="musicPlaylistId"
                            :options="$musicOptions" optionValue="value" optionLabel="label" :nullable="true" nullLabel="– keine –" />
                    </div>
                    <x-ui-button type="submit" variant="primary">Speichern</x-ui-button>
                </form>
            </x-ui-panel>

            <x-ui-panel title="Zeitpläne" subtitle="Übersteuern die Standard-Wiedergabeliste in bestimmten Zeitfenstern (höchste Priorität gewinnt)">
                <div class="divide-y divide-[var(--ui-border)]/40">
                    @forelse($schedules as $schedule)
                        <div class="flex items-center justify-between p-3" wire:key="sched-{{ $schedule->id }}">
                            <div>
                                <div class="font-medium text-[var(--ui-secondary)]">
                                    {{ $schedule->playlist?->name }}
                                    @if($schedule->musicPlaylist) <span class="text-[var(--ui-muted)]">+ {{ $schedule->musicPlaylist->name }}</span> @endif
                                </div>
                                <div class="text-xs text-[var(--ui-muted)]">
                                    {{ collect($schedule->days_of_week)->map(fn($d) => $weekdays[$d] ?? $d)->implode(', ') }}
                                    · {{ \Illuminate\Support\Str::of($schedule->start_time)->substr(0,5) }}–{{ \Illuminate\Support\Str::of($schedule->end_time)->substr(0,5) }}
                                    · Priorität {{ $schedule->priority }}
                                </div>
                            </div>
                            <button wire:click="deleteSchedule({{ $schedule->id }})" class="p-1.5 rounded text-[var(--ui-muted)] hover:text-red-600">
                                @svg('heroicon-o-trash', 'w-4 h-4')
                            </button>
                        </div>
                    @empty
                        <div class="p-6 text-center text-[var(--ui-muted)] text-sm">Keine Zeitpläne – es läuft immer die Standard-Wiedergabeliste.</div>
                    @endforelse
                </div>

                <form wire:submit="addSchedule" class="space-y-4 p-4 border-t border-[var(--ui-border)]/40">
                    <div class="flex flex-wrap gap-2">
                        @foreach($weekdays as $num => $label)
                            <label class="inline-flex items-center gap-1.5 px-2.5 py-1.5 rounded border border-[var(--ui-border)] cursor-pointer text-sm">
                                <input type="checkbox" value="{{ $num }}" wire:model="schedDays" class="rounded">
                                {{ $label }}
                            </label>
                        @endforeach
                    </div>
                    @error('schedDays')<span class="text-xs text-red-600">{{ $message }}</span>@enderror

                    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3">
                        <div>
                            <label class="block text-xs text-[var(--ui-muted)] mb-1">Von</label>
                            <input type="time" wire:model="schedStart" class="w-full px-2 py-1.5 rounded border border-[var(--ui-border)]">
                        </div>
                        <div>
                            <label class="block text-xs text-[var(--ui-muted)] mb-1">Bis</label>
                            <input type="time" wire:model="schedEnd" class="w-full px-2 py-1.5 rounded border border-[var(--ui-border)]">
                        </div>
                        <x-ui-input-select name="schedPlaylistId" label="Wiedergabeliste" wire:model="schedPlaylistId"
                            :options="$visualOptions" optionValue="value" optionLabel="label" :nullable="true" nullLabel="– wählen –" />
                        <x-ui-input-select name="schedMusicId" label="Musik (optional)" wire:model="schedMusicId"
                            :options="$musicOptions" optionValue="value" optionLabel="label" :nullable="true" nullLabel="– keine –" />
                        <div>
                            <label class="block text-xs text-[var(--ui-muted)] mb-1">Priorität</label>
                            <input type="number" wire:model="schedPriority" class="w-full px-2 py-1.5 rounded border border-[var(--ui-border)]">
                        </div>
                    </div>
                    @error('schedPlaylistId')<span class="text-xs text-red-600">{{ $message }}</span>@enderror

                    <x-ui-button type="submit" variant="primary">Zeitplan hinzufügen</x-ui-button>
                </form>
            </x-ui-panel>
        </div>
    </x-ui-page-container>
</x-ui-page>
