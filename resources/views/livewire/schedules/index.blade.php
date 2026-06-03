<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="Zeitpläne" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'Digital Signage', 'href' => route('signage.dashboard'), 'icon' => 'tv'],
            ['label' => 'Zeitpläne'],
        ]" />
    </x-slot>

    <x-ui-page-container>
        <div class="space-y-6">
            @if(session('signage_message'))
                <div class="p-3 rounded bg-green-100 text-green-800 text-sm">{{ session('signage_message') }}</div>
            @endif

            <x-signage-panel title="Neuer Zeitplan" subtitle="Zeitgesteuerte Pläne, die einem Bildschirm zugewiesen werden">
                <form wire:submit="create" class="flex flex-col sm:flex-row items-start sm:items-end gap-3 p-4">
                    <div class="flex-1 w-full">
                        <x-ui-input-text name="name" label="Name" wire:model="name" placeholder="z.B. Wochenplan Empfang" />
                        @error('name')<span class="text-xs text-red-600">{{ $message }}</span>@enderror
                    </div>
                    <x-ui-button type="submit" variant="primary">Erstellen</x-ui-button>
                </form>
            </x-signage-panel>

            <x-signage-panel title="Zeitpläne">
                <div class="divide-y divide-[var(--ui-border)]/40">
                    @forelse($schedules as $schedule)
                        <div class="flex items-center justify-between p-4" wire:key="sched-{{ $schedule->id }}">
                            <a href="{{ route('signage.schedules.show', $schedule) }}" wire:navigate class="flex-1">
                                <div class="font-medium text-[var(--ui-secondary)]">{{ $schedule->name }}</div>
                                <div class="text-xs text-[var(--ui-muted)]">{{ $schedule->rules_count }} Regel(n)</div>
                            </a>
                            <button wire:click="deleteSchedule({{ $schedule->id }})"
                                    wire:confirm="Zeitplan löschen?"
                                    class="p-1.5 rounded text-[var(--ui-muted)] hover:text-red-600">
                                @svg('heroicon-o-trash', 'w-4 h-4')
                            </button>
                        </div>
                    @empty
                        <div class="p-6 text-center text-[var(--ui-muted)] text-sm">Noch keine Zeitpläne.</div>
                    @endforelse
                </div>
            </x-signage-panel>
        </div>
    </x-ui-page-container>
</x-ui-page>
