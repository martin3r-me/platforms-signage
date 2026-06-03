@php
    $visualOptions = $visualPlaylists->map(fn($p) => ['value' => $p->id, 'label' => $p->name])->values()->all();
    $musicOptions = $musicPlaylists->map(fn($p) => ['value' => $p->id, 'label' => $p->name])->values()->all();
    $weekdays = [1 => 'Mo', 2 => 'Di', 3 => 'Mi', 4 => 'Do', 5 => 'Fr', 6 => 'Sa', 7 => 'So'];
    $weekdayNames = [1 => 'Montag', 2 => 'Dienstag', 3 => 'Mittwoch', 4 => 'Donnerstag', 5 => 'Freitag', 6 => 'Samstag', 7 => 'Sonntag'];
    $hourHeight = 48; // px pro Stunde
    $pxPerMin = $hourHeight / 60;
@endphp
<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar :title="$schedule->name" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'Digital Signage', 'href' => route('signage.dashboard'), 'icon' => 'tv'],
            ['label' => 'Zeitpläne', 'href' => route('signage.schedules.index')],
            ['label' => $schedule->name],
        ]" />
    </x-slot>

    <x-ui-page-container>
        <div class="space-y-6 pt-4">
            @if(session('signage_message'))
                <div class="p-3 rounded bg-green-100 text-green-800 text-sm">{{ session('signage_message') }}</div>
            @endif

            <x-signage-panel icon="clock" title="Zeitplan">
                <form wire:submit="rename" class="flex flex-col sm:flex-row items-start sm:items-end gap-3 p-4">
                    <div class="flex-1 w-full">
                        <x-ui-input-text name="name" label="Name" wire:model="name" />
                        @error('name')<span class="text-xs text-red-600">{{ $message }}</span>@enderror
                    </div>
                    <x-ui-button type="submit" variant="primary">Speichern</x-ui-button>
                </form>
            </x-signage-panel>

            <x-signage-panel icon="calendar-days" title="Wochenplan" subtitle="Pro Zeitfenster eine Wiedergabeliste – höchste Priorität gewinnt, sonst greift die Standard-Wiedergabeliste des Bildschirms">
                @if($scheduleRules->isEmpty())
                    <div class="p-6 text-center text-[var(--ui-muted)] text-sm">
                        Noch keine Regeln – ohne Regel greift immer die Standard-Wiedergabeliste des Bildschirms.
                        Lege unten dein erstes Zeitfenster an.
                    </div>
                @endif

                {{-- Wochen-Kalender (Mo–So), farbige Blöcke je Regel --}}
                <div class="overflow-x-auto p-4">
                    <div class="min-w-[760px]">
                        {{-- Kopfzeile: Wochentage --}}
                        <div class="flex border-b border-[var(--ui-border)]/50">
                            <div class="w-12 shrink-0"></div>
                            @foreach($weekdayNames as $num => $label)
                                <div class="flex-1 min-w-[96px] text-center py-2 text-xs font-semibold text-[var(--ui-secondary)] border-l border-[var(--ui-border)]/40">
                                    {{ $label }}
                                </div>
                            @endforeach
                        </div>

                        {{-- Körper: Stunden-Gutter + 7 Tagesspalten --}}
                        <div class="flex">
                            <div class="w-12 shrink-0">
                                @for($h = $calendar['startHour']; $h < $calendar['endHour']; $h++)
                                    <div class="relative" style="height: {{ $hourHeight }}px">
                                        <span class="absolute -top-2 right-1.5 text-[10px] text-[var(--ui-muted)]">{{ sprintf('%02d:00', $h) }}</span>
                                    </div>
                                @endfor
                            </div>

                            @foreach($weekdayNames as $num => $label)
                                <div class="flex-1 min-w-[96px] relative border-l border-[var(--ui-border)]/40"
                                     style="height: {{ ($calendar['endHour'] - $calendar['startHour']) * $hourHeight }}px">
                                    {{-- Stundenlinien --}}
                                    @for($h = $calendar['startHour']; $h < $calendar['endHour']; $h++)
                                        <div class="border-t border-[var(--ui-border)]/25" style="height: {{ $hourHeight }}px"></div>
                                    @endfor

                                    {{-- Blöcke der Regeln --}}
                                    @foreach($calendar['blocks'][$num] ?? [] as $b)
                                        <div class="group absolute left-1 right-1 rounded-md px-1.5 py-1 overflow-hidden text-white shadow-sm"
                                             style="top: {{ $b['top'] * $pxPerMin }}px; height: {{ max(20, $b['len'] * $pxPerMin) }}px; background: {{ $b['color'] }}"
                                             title="{{ $b['time'] }} · {{ $b['playlist'] }}{{ $b['music'] ? ' + '.$b['music'] : '' }} · Priorität {{ $b['priority'] }}">
                                            <div class="text-[10px] font-semibold leading-tight">{{ $b['time'] }}</div>
                                            <div class="text-[11px] leading-tight truncate">{{ $b['playlist'] }}</div>
                                            @if($b['music'])
                                                <div class="text-[10px] opacity-80 leading-tight truncate">♪ {{ $b['music'] }}</div>
                                            @endif
                                            <button wire:click="deleteRule({{ $b['ruleId'] }})"
                                                    wire:confirm="Diese Regel wirklich löschen?"
                                                    class="absolute top-0.5 right-0.5 p-0.5 rounded bg-black/25 opacity-0 group-hover:opacity-100 transition"
                                                    title="Regel löschen">
                                                @svg('heroicon-o-trash', 'w-3 h-3')
                                            </button>
                                        </div>
                                    @endforeach
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>

                <form wire:submit="addRule" class="space-y-4 p-4 border-t border-[var(--ui-border)]/40">
                    <div class="text-sm font-medium text-[var(--ui-secondary)]">Neues Zeitfenster hinzufügen</div>
                    <div class="flex flex-wrap gap-2">
                        @foreach($weekdays as $num => $label)
                            <label class="inline-flex items-center gap-1.5 px-2.5 py-1.5 rounded border border-[var(--ui-border)] cursor-pointer text-sm">
                                <input type="checkbox" value="{{ $num }}" wire:model="ruleDays" class="rounded">
                                {{ $label }}
                            </label>
                        @endforeach
                    </div>
                    @error('ruleDays')<span class="text-xs text-red-600">{{ $message }}</span>@enderror

                    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3">
                        <div>
                            <label class="block text-xs text-[var(--ui-muted)] mb-1">Von</label>
                            <input type="time" wire:model="ruleStart" class="w-full px-2 py-1.5 rounded border border-[var(--ui-border)]">
                        </div>
                        <div>
                            <label class="block text-xs text-[var(--ui-muted)] mb-1">Bis</label>
                            <input type="time" wire:model="ruleEnd" class="w-full px-2 py-1.5 rounded border border-[var(--ui-border)]">
                        </div>
                        <x-ui-input-select name="rulePlaylistId" label="Wiedergabeliste" wire:model="rulePlaylistId"
                            :options="$visualOptions" optionValue="value" optionLabel="label" :nullable="true" nullLabel="– wählen –" />
                        <x-ui-input-select name="ruleMusicId" label="Musik-Liste (optional)" wire:model="ruleMusicId"
                            :options="$musicOptions" optionValue="value" optionLabel="label" :nullable="true" nullLabel="– keine –" />
                        <div>
                            <label class="block text-xs text-[var(--ui-muted)] mb-1">Priorität</label>
                            <input type="number" wire:model="rulePriority" class="w-full px-2 py-1.5 rounded border border-[var(--ui-border)]">
                        </div>
                    </div>
                    @error('rulePlaylistId')<span class="text-xs text-red-600">{{ $message }}</span>@enderror

                    <x-ui-button type="submit" variant="primary">Regel hinzufügen</x-ui-button>
                </form>
            </x-signage-panel>
        </div>
    </x-ui-page-container>
</x-ui-page>
