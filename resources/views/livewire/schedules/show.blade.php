@php
    $visualOptions = $visualPlaylists->map(fn($p) => ['value' => $p->id, 'label' => $p->name])->values()->all();
    $musicOptions = $musicPlaylists->map(fn($p) => ['value' => $p->id, 'label' => $p->name])->values()->all();
    $weekdays = [1 => 'Mo', 2 => 'Di', 3 => 'Mi', 4 => 'Do', 5 => 'Fr', 6 => 'Sa', 7 => 'So'];
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

            <x-signage-panel icon="adjustments-horizontal" title="Regeln" subtitle="Pro Zeitfenster eine Wiedergabeliste – höchste Priorität gewinnt, sonst greift die Standard-Wiedergabeliste des Bildschirms">
                <div class="divide-y divide-[var(--ui-border)]/40">
                    @forelse($scheduleRules as $rule)
                        <div class="flex items-center justify-between p-3" wire:key="rule-{{ $rule->id }}">
                            <div>
                                <div class="font-medium text-[var(--ui-secondary)]">
                                    {{ $rule->playlist?->name }}
                                    @if($rule->musicPlaylist) <span class="text-[var(--ui-muted)]">+ {{ $rule->musicPlaylist->name }}</span> @endif
                                </div>
                                <div class="text-xs text-[var(--ui-muted)]">
                                    {{ collect($rule->days_of_week)->map(fn($d) => $weekdays[$d] ?? $d)->implode(', ') }}
                                    · {{ \Illuminate\Support\Str::of($rule->start_time)->substr(0,5) }}–{{ \Illuminate\Support\Str::of($rule->end_time)->substr(0,5) }}
                                    · Priorität {{ $rule->priority }}
                                </div>
                            </div>
                            <button wire:click="deleteRule({{ $rule->id }})" class="p-1.5 rounded text-[var(--ui-muted)] hover:text-red-600">
                                @svg('heroicon-o-trash', 'w-4 h-4')
                            </button>
                        </div>
                    @empty
                        <div class="p-6 text-center text-[var(--ui-muted)] text-sm">Keine Regeln – ohne Regel greift immer die Standard-Wiedergabeliste des Bildschirms.</div>
                    @endforelse
                </div>

                <form wire:submit="addRule" class="space-y-4 p-4 border-t border-[var(--ui-border)]/40">
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
