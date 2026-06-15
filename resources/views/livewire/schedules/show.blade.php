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

            <x-signage-panel color="amber" icon="clock" title="Zeitplan">
                <form wire:submit="rename" class="flex flex-col sm:flex-row items-start sm:items-end gap-3 p-4">
                    <div class="flex-1 w-full">
                        <x-ui-input-text name="name" label="Name" wire:model="name" />
                        @error('name')<span class="text-xs text-red-600">{{ $message }}</span>@enderror
                    </div>
                    <x-ui-button type="submit" variant="primary">Speichern</x-ui-button>
                </form>
            </x-signage-panel>

            <x-signage-panel color="amber" icon="calendar-days" title="Wochenplan" subtitle="Pro Zeitfenster eine Wiedergabeliste – höchste Priorität gewinnt, sonst greift die Standard-Wiedergabeliste des Bildschirms">
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
                        <div class="flex" x-data="{}">
                            <div class="w-12 shrink-0">
                                @for($h = $calendar['startHour']; $h < $calendar['endHour']; $h++)
                                    <div class="relative" style="height: {{ $hourHeight }}px">
                                        <span class="absolute -top-2 right-1.5 text-[10px] text-[var(--ui-muted)]">{{ sprintf('%02d:00', $h) }}</span>
                                    </div>
                                @endfor
                            </div>

                            @foreach($weekdayNames as $num => $label)
                                <div class="flex-1 min-w-[96px] relative border-l border-[var(--ui-border)]/40 cursor-pointer hover:bg-[var(--ui-primary)]/5 group/col"
                                     style="height: {{ ($calendar['endHour'] - $calendar['startHour']) * $hourHeight }}px"
                                     x-on:click="$wire.openRuleModal({{ $num }}, {{ $calendar['startHour'] }} + Math.floor(($event.clientY - $el.getBoundingClientRect().top) / {{ $hourHeight }}))"
                                     title="Klicken, um hier ein Zeitfenster anzulegen">
                                    {{-- Stundenlinien --}}
                                    @for($h = $calendar['startHour']; $h < $calendar['endHour']; $h++)
                                        <div class="border-t border-[var(--ui-border)]/25" style="height: {{ $hourHeight }}px"></div>
                                    @endfor

                                    {{-- Blöcke der Regeln (Klick nicht an die Spalte weiterreichen) --}}
                                    @foreach($calendar['blocks'][$num] ?? [] as $b)
                                        <div class="group absolute left-1 right-1 rounded-md px-1.5 py-1 overflow-hidden text-white shadow-sm cursor-default"
                                             x-on:click.stop="null"
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

                <div class="flex items-center gap-2 p-4 border-t border-[var(--ui-border)]/40">
                    <x-ui-button wire:click="openRuleModal" variant="primary">
                        @svg('heroicon-o-plus', 'w-4 h-4') Zeitfenster hinzufügen
                    </x-ui-button>
                    <span class="text-xs text-[var(--ui-muted)]">Tipp: direkt in den Kalender klicken, um ein Zeitfenster anzulegen.</span>
                </div>
            </x-signage-panel>
        </div>

        {{-- Dialog: Zeitfenster anlegen --}}
        @if($showRuleModal)
            <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
                 wire:click.self="closeRuleModal" wire:key="rule-modal">
                <div class="bg-[var(--ui-bg,#fff)] rounded-xl shadow-2xl w-full max-w-xl max-h-[90vh] overflow-y-auto">
                    <div class="flex items-center justify-between px-5 py-3 border-b border-[var(--ui-border)]/40">
                        <h3 class="font-semibold text-[var(--ui-secondary)]">Zeitfenster anlegen</h3>
                        <button wire:click="closeRuleModal" class="p-1 rounded text-[var(--ui-muted)] hover:text-[var(--ui-secondary)]">
                            @svg('heroicon-o-x-mark', 'w-5 h-5')
                        </button>
                    </div>

                    <form wire:submit="addRule" class="space-y-4 p-5">
                        <div>
                            <label class="block text-xs text-[var(--ui-muted)] mb-1.5">Wochentage</label>
                            <div class="flex flex-wrap gap-2">
                                @foreach($weekdays as $num => $label)
                                    <label class="inline-flex items-center gap-1.5 px-2.5 py-1.5 rounded border border-[var(--ui-border)] cursor-pointer text-sm">
                                        <input type="checkbox" value="{{ $num }}" wire:model="ruleDays" class="rounded">
                                        {{ $label }}
                                    </label>
                                @endforeach
                            </div>
                            @error('ruleDays')<span class="text-xs text-red-600">{{ $message }}</span>@enderror
                        </div>

                        <label class="inline-flex items-center gap-2 text-sm cursor-pointer">
                            <input type="checkbox" wire:model.live="ruleAllDay" class="rounded">
                            Ganztägig (00:00–24:00)
                        </label>

                        <div class="grid grid-cols-2 gap-3" @if($ruleAllDay) style="display:none" @endif>
                            <div>
                                <label class="block text-xs text-[var(--ui-muted)] mb-1">Von</label>
                                <input type="time" wire:model="ruleStart" class="w-full px-2 py-1.5 rounded border border-[var(--ui-border)]">
                            </div>
                            <div>
                                <label class="block text-xs text-[var(--ui-muted)] mb-1">Bis</label>
                                <input type="time" wire:model="ruleEnd" class="w-full px-2 py-1.5 rounded border border-[var(--ui-border)]">
                            </div>
                        </div>

                        <x-ui-input-select name="rulePlaylistId" label="Wiedergabeliste" wire:model="rulePlaylistId"
                            :options="$visualOptions" optionValue="value" optionLabel="label" :nullable="true" nullLabel="– wählen –" />
                        @error('rulePlaylistId')<span class="text-xs text-red-600">{{ $message }}</span>@enderror

                        <x-ui-input-select name="ruleMusicId" label="Musik-Liste (optional)" wire:model="ruleMusicId"
                            :options="$musicOptions" optionValue="value" optionLabel="label" :nullable="true" nullLabel="– keine –" />

                        <div>
                            <label class="block text-xs text-[var(--ui-muted)] mb-1">Priorität (höher gewinnt bei Überschneidung)</label>
                            <input type="number" wire:model="rulePriority" class="w-full px-2 py-1.5 rounded border border-[var(--ui-border)]">
                        </div>

                        <div class="flex items-center justify-end gap-2 pt-1">
                            <x-ui-button type="button" variant="secondary" wire:click="closeRuleModal">Abbrechen</x-ui-button>
                            <x-ui-button type="submit" variant="primary">Hinzufügen</x-ui-button>
                        </div>
                    </form>
                </div>
            </div>
        @endif
    </x-ui-page-container>
</x-ui-page>
