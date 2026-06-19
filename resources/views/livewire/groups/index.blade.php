<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="Bildschirm-Gruppen" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'Digital Signage', 'href' => route('signage.dashboard'), 'icon' => 'tv'],
            ['label' => 'Gruppen'],
        ]">
            <x-ui-button wire:click="openCreateModal" variant="primary" size="sm">
                @svg('heroicon-o-plus', 'w-4 h-4')
                Gruppe anlegen
            </x-ui-button>
        </x-ui-page-actionbar>
    </x-slot>

    <x-ui-page-container>
        <div class="space-y-4 pt-4">
            @if(session('signage_message'))
                <div class="p-3 rounded bg-green-100 text-green-800 text-sm">{{ session('signage_message') }}</div>
            @endif

            <x-signage-panel color="indigo" icon="rectangle-group" title="Gruppen" subtitle="Bildschirme bündeln und Einstellungen gemeinsam zuweisen">
                <div class="divide-y divide-[var(--ui-border)]/40">
                    @forelse($groups as $group)
                        <div class="flex items-center justify-between p-4" wire:key="group-{{ $group->id }}">
                            <a href="{{ route('signage.groups.show', $group) }}" wire:navigate class="flex-1 min-w-0">
                                <div class="font-medium text-[var(--ui-secondary)]">{{ $group->name }}</div>
                                <div class="text-xs text-[var(--ui-muted)]">{{ $group->screens_count }} Bildschirm(e)</div>
                            </a>
                            <button wire:click="deleteGroup({{ $group->id }})"
                                    wire:confirm="Gruppe „{{ $group->name }}“ löschen? (Bildschirme bleiben erhalten)"
                                    class="p-1.5 rounded text-[var(--ui-muted)] hover:text-red-600">
                                @svg('heroicon-o-trash', 'w-4 h-4')
                            </button>
                        </div>
                    @empty
                        <div class="p-8 text-center text-[var(--ui-muted)] text-sm">
                            Noch keine Gruppen. Lege eine an, um mehrere Bildschirme gemeinsam zu verwalten.
                        </div>
                    @endforelse
                </div>
            </x-signage-panel>
        </div>

        @if($showCreateModal)
            <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4" wire:click.self="closeCreateModal">
                <div class="bg-[var(--ui-bg,#fff)] rounded-xl shadow-2xl w-full max-w-md">
                    <div class="px-5 py-4 border-b border-[var(--ui-border)]/40">
                        <h3 class="font-semibold text-[var(--ui-secondary)]">Gruppe anlegen</h3>
                    </div>
                    <form wire:submit="create" class="p-5 space-y-4">
                        <div>
                            <x-ui-input-text name="name" label="Name" wire:model="name" placeholder="z.B. Foyer-Bildschirme" />
                            @error('name')<span class="text-xs text-red-600">{{ $message }}</span>@enderror
                        </div>
                        <div class="flex items-center justify-end gap-2">
                            <x-ui-button type="button" variant="secondary" wire:click="closeCreateModal">Abbrechen</x-ui-button>
                            <x-ui-button type="submit" variant="primary">Anlegen</x-ui-button>
                        </div>
                    </form>
                </div>
            </div>
        @endif
    </x-ui-page-container>
</x-ui-page>
