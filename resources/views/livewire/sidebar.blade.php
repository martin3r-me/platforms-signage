<div>
    {{-- Modul-Header --}}
    <div x-show="!collapsed" class="p-3 text-sm italic text-[var(--ui-secondary)] uppercase border-b border-[var(--ui-border)] mb-2">
        Digital Signage
    </div>

    {{-- Expanded: gruppierte Navigation --}}
    <div x-show="!collapsed">
        <x-ui-sidebar-list label="Übersicht">
            <x-ui-sidebar-item :href="route('signage.dashboard')">
                @svg('heroicon-o-home', 'w-4 h-4 text-[var(--ui-secondary)]')
                <span class="ml-2 text-sm">Dashboard</span>
            </x-ui-sidebar-item>
        </x-ui-sidebar-list>

        <x-ui-sidebar-list label="Inhalte">
            <x-ui-sidebar-item :href="route('signage.media.index')">
                @svg('heroicon-o-photo', 'w-4 h-4 text-[var(--ui-secondary)]')
                <span class="ml-2 text-sm">Medien</span>
            </x-ui-sidebar-item>
            <x-ui-sidebar-item :href="route('signage.playlists.index')">
                @svg('heroicon-o-queue-list', 'w-4 h-4 text-[var(--ui-secondary)]')
                <span class="ml-2 text-sm">Wiedergabelisten</span>
            </x-ui-sidebar-item>
        </x-ui-sidebar-list>

        <x-ui-sidebar-list label="Geräte">
            <x-ui-sidebar-item :href="route('signage.screens.index')">
                @svg('heroicon-o-computer-desktop', 'w-4 h-4 text-[var(--ui-secondary)]')
                <span class="ml-2 text-sm">Bildschirme</span>
            </x-ui-sidebar-item>
        </x-ui-sidebar-list>
    </div>

    {{-- Collapsed: nur Icons --}}
    <div x-show="collapsed" class="px-2 py-2">
        <div class="flex flex-col gap-2">
            <a href="{{ route('signage.dashboard') }}" wire:navigate class="flex items-center justify-center p-2 rounded-md text-[var(--ui-secondary)] hover:bg-[var(--ui-muted-5)]" title="Dashboard">
                @svg('heroicon-o-home', 'w-5 h-5')
            </a>
            <a href="{{ route('signage.media.index') }}" wire:navigate class="flex items-center justify-center p-2 rounded-md text-[var(--ui-secondary)] hover:bg-[var(--ui-muted-5)]" title="Medien">
                @svg('heroicon-o-photo', 'w-5 h-5')
            </a>
            <a href="{{ route('signage.playlists.index') }}" wire:navigate class="flex items-center justify-center p-2 rounded-md text-[var(--ui-secondary)] hover:bg-[var(--ui-muted-5)]" title="Wiedergabelisten">
                @svg('heroicon-o-queue-list', 'w-5 h-5')
            </a>
            <a href="{{ route('signage.screens.index') }}" wire:navigate class="flex items-center justify-center p-2 rounded-md text-[var(--ui-secondary)] hover:bg-[var(--ui-muted-5)]" title="Bildschirme">
                @svg('heroicon-o-computer-desktop', 'w-5 h-5')
            </a>
        </div>
    </div>
</div>
