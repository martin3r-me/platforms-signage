<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="Zeitpläne" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'Digital Signage', 'href' => route('signage.dashboard'), 'icon' => 'tv'],
            ['label' => 'Zeitpläne'],
        ]">
            <x-ui-button wire:click="openCreateModal" variant="primary" size="sm">
                @svg('heroicon-o-plus', 'w-4 h-4')
                Neuer Zeitplan
            </x-ui-button>
        </x-ui-page-actionbar>
    </x-slot>

    <x-ui-page-container>
        <div class="space-y-6 pt-4">
            @if(session('signage_message'))
                <div class="p-3 rounded bg-green-100 text-green-800 text-sm">{{ session('signage_message') }}</div>
            @endif

            <x-signage-panel color="amber" icon="clock" title="Zeitpläne">
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

        {{-- Dialog: Neuer Zeitplan --}}
        @if($showCreateModal)
            <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
                 wire:click.self="closeCreateModal" wire:key="schedule-create-modal">
                <div class="bg-[var(--ui-bg,#fff)] rounded-xl shadow-2xl w-full max-w-lg">
                    <div class="flex items-center justify-between px-5 py-3 border-b border-[var(--ui-border)]/40">
                        <h3 class="font-semibold text-[var(--ui-secondary)]">Neuer Zeitplan</h3>
                        <button wire:click="closeCreateModal" class="p-1 rounded text-[var(--ui-muted)] hover:text-[var(--ui-secondary)]">
                            @svg('heroicon-o-x-mark', 'w-5 h-5')
                        </button>
                    </div>
                    <form wire:submit="create" class="space-y-4 p-5">
                        <div>
                            <x-ui-input-text name="name" label="Name" wire:model="name" placeholder="z.B. Wochenplan Empfang" />
                            @error('name')<span class="text-xs text-red-600">{{ $message }}</span>@enderror
                        </div>
                        <p class="text-xs text-[var(--ui-muted)]">Zeitgesteuerte Pläne, die einem Bildschirm zugewiesen werden.</p>
                        <div class="flex items-center justify-end gap-2 pt-1">
                            <x-ui-button type="button" variant="secondary" wire:click="closeCreateModal">Abbrechen</x-ui-button>
                            <x-ui-button type="submit" variant="primary">Erstellen</x-ui-button>
                        </div>
                    </form>
                </div>
            </div>
        @endif
    </x-ui-page-container>
</x-ui-page>
