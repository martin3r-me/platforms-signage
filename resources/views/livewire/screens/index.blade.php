<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="Bildschirme" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'Digital Signage', 'href' => route('signage.dashboard'), 'icon' => 'tv'],
            ['label' => 'Bildschirme'],
        ]">
            <div class="flex items-center gap-2">
                @if($firetvApk)
                    <x-ui-button variant="secondary" size="sm" :href="route('signage.firetv.apk')">
                        @svg('heroicon-o-arrow-down-tray', 'w-4 h-4')
                        Für Fire TV herunterladen
                    </x-ui-button>
                @endif
                <x-ui-button wire:click="openCreateModal" variant="primary" size="sm">
                    @svg('heroicon-o-link', 'w-4 h-4')
                    Bildschirm koppeln
                </x-ui-button>
            </div>
        </x-ui-page-actionbar>
    </x-slot>

    <x-ui-page-container>
        <div class="space-y-6 pt-4">
            @if(session('signage_message'))
                <div class="p-3 rounded bg-green-100 text-green-800 text-sm">{{ session('signage_message') }}</div>
            @endif

            <x-signage-panel icon="computer-desktop" title="Gekoppelte Bildschirme">
                <div class="divide-y divide-[var(--ui-border)]/40">
                    @forelse($screens as $screen)
                        <div class="flex items-center justify-between p-4" wire:key="screen-{{ $screen->id }}">
                            <a href="{{ route('signage.screens.show', $screen) }}" wire:navigate class="flex items-center gap-3 flex-1 min-w-0">
                                <x-signage-badge :color="$screen->isOnline() ? 'green' : 'gray'" dot class="shrink-0">
                                    {{ $screen->isOnline() ? 'Online' : 'Offline' }}
                                </x-signage-badge>
                                <div class="min-w-0">
                                    <div class="font-medium text-[var(--ui-secondary)] truncate">{{ $screen->name }}</div>
                                    <div class="text-xs text-[var(--ui-muted)] truncate">
                                        {{ $screen->isOnline() ? 'Gerade erreichbar' : ($screen->last_seen_at ? 'Zuletzt '.$screen->last_seen_at->diffForHumans() : 'Noch nie online') }}
                                        @if($screen->defaultPlaylist) · {{ $screen->defaultPlaylist->name }} @endif
                                    </div>
                                </div>
                            </a>
                            <div class="flex items-center gap-2">
                                <x-ui-button size="sm" variant="secondary" wire:click="reload({{ $screen->id }})" title="Neuladen erzwingen">
                                    @svg('heroicon-o-arrow-path', 'w-4 h-4')
                                </x-ui-button>
                                <button wire:click="deleteScreen({{ $screen->id }})"
                                        wire:confirm="Bildschirm „{{ $screen->name }}“ entfernen?"
                                        class="p-1.5 rounded text-[var(--ui-muted)] hover:text-red-600">
                                    @svg('heroicon-o-trash', 'w-4 h-4')
                                </button>
                            </div>
                        </div>
                    @empty
                        <div class="p-6 text-center text-[var(--ui-muted)]">Noch keine Bildschirme gekoppelt.</div>
                    @endforelse
                </div>
            </x-signage-panel>

            {{-- Onboarding: Fire-TV-Einrichtung --}}
            @if($firetvApk)
                <div x-data="{ open: true }">
                    <x-signage-panel color="indigo" icon="tv" title="Fire-TV-Bildschirm einrichten"
                        subtitle="In 4 Schritten auf dem Fire TV Stick einrichten">
                        <x-slot name="actions">
                            <button type="button" x-on:click="open = !open"
                                    class="inline-flex items-center gap-1 text-xs font-medium text-[var(--ui-secondary)] hover:text-[rgb(var(--ui-primary-rgb))]">
                                <span x-text="open ? 'Ausblenden' : 'Anleitung'"></span>
                                @svg('heroicon-o-chevron-down', 'w-4 h-4 transition-transform')
                            </button>
                        </x-slot>

                        <div x-show="open" x-cloak class="p-5">
                            <ol class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4">
                                <li class="rounded-xl border border-[var(--ui-border)]/50 bg-[var(--ui-muted-5)]/40 p-4">
                                    <div class="flex items-center gap-2 mb-2">
                                        <span class="flex h-7 w-7 items-center justify-center rounded-full bg-indigo-100 text-indigo-600 text-sm font-bold">1</span>
                                        @svg('heroicon-o-arrow-down-tray', 'w-4 h-4 text-[var(--ui-muted)]')
                                    </div>
                                    <div class="font-semibold text-sm text-[var(--ui-secondary)]">App laden</div>
                                    <p class="mt-1 text-xs text-[var(--ui-muted)] leading-relaxed">
                                        Oben auf <strong>Für Fire TV herunterladen</strong> – oder am TV in der App
                                        <strong>Downloader</strong> diese Adresse öffnen:
                                    </p>
                                    <div class="mt-1.5 flex items-center gap-1" x-data="{ copied: false }">
                                        <code class="flex-1 min-w-0 overflow-x-auto whitespace-nowrap select-all rounded bg-[var(--ui-muted-5)] px-2 py-1 text-[10px] text-[var(--ui-secondary)]">{{ url('/signage/firetv/app.apk') }}</code>
                                        <button type="button"
                                                x-on:click="navigator.clipboard.writeText('{{ url('/signage/firetv/app.apk') }}'); copied = true; setTimeout(() => copied = false, 1500)"
                                                x-bind:title="copied ? 'Kopiert!' : 'Adresse kopieren'"
                                                class="shrink-0 p-1.5 rounded border border-[var(--ui-border)] text-[var(--ui-muted)] hover:text-[rgb(var(--ui-primary-rgb))] hover:border-[rgb(var(--ui-primary-rgb))] transition">
                                            <span x-show="!copied">@svg('heroicon-o-clipboard-document', 'w-4 h-4')</span>
                                            <span x-show="copied" x-cloak>@svg('heroicon-o-check', 'w-4 h-4 text-green-600')</span>
                                        </button>
                                    </div>
                                </li>

                                <li class="rounded-xl border border-[var(--ui-border)]/50 bg-[var(--ui-muted-5)]/40 p-4">
                                    <div class="flex items-center gap-2 mb-2">
                                        <span class="flex h-7 w-7 items-center justify-center rounded-full bg-indigo-100 text-indigo-600 text-sm font-bold">2</span>
                                        @svg('heroicon-o-lock-open', 'w-4 h-4 text-[var(--ui-muted)]')
                                    </div>
                                    <div class="font-semibold text-sm text-[var(--ui-secondary)]">Installation erlauben</div>
                                    <p class="mt-1 text-xs text-[var(--ui-muted)] leading-relaxed">
                                        Am Fire TV unter <em>Einstellungen → Mein Fire TV → Entwickleroptionen</em>
                                        „Apps unbekannter Herkunft" für Downloader aktivieren.
                                    </p>
                                </li>

                                <li class="rounded-xl border border-[var(--ui-border)]/50 bg-[var(--ui-muted-5)]/40 p-4">
                                    <div class="flex items-center gap-2 mb-2">
                                        <span class="flex h-7 w-7 items-center justify-center rounded-full bg-indigo-100 text-indigo-600 text-sm font-bold">3</span>
                                        @svg('heroicon-o-play-circle', 'w-4 h-4 text-[var(--ui-muted)]')
                                    </div>
                                    <div class="font-semibold text-sm text-[var(--ui-secondary)]">Installieren & starten</div>
                                    <p class="mt-1 text-xs text-[var(--ui-muted)] leading-relaxed">
                                        APK installieren und <strong>Digital Signage</strong> öffnen – es erscheint ein
                                        großer <strong>Kopplungs-Code</strong>.
                                    </p>
                                </li>

                                <li class="rounded-xl border border-[var(--ui-border)]/50 bg-[var(--ui-muted-5)]/40 p-4">
                                    <div class="flex items-center gap-2 mb-2">
                                        <span class="flex h-7 w-7 items-center justify-center rounded-full bg-indigo-100 text-indigo-600 text-sm font-bold">4</span>
                                        @svg('heroicon-o-link', 'w-4 h-4 text-[var(--ui-muted)]')
                                    </div>
                                    <div class="font-semibold text-sm text-[var(--ui-secondary)]">Koppeln</div>
                                    <p class="mt-1 text-xs text-[var(--ui-muted)] leading-relaxed">
                                        Code oben über <strong>Bildschirm koppeln</strong> eintragen. Fertig – der TV
                                        spielt die zugewiesene Wiedergabeliste.
                                    </p>
                                </li>
                            </ol>

                            <div class="mt-4 flex items-start gap-2 rounded-lg border border-[rgb(var(--ui-primary-rgb))]/15 bg-[rgb(var(--ui-primary-rgb))]/5 px-3 py-2">
                                @svg('heroicon-o-information-circle', 'w-4 h-4 text-[rgb(var(--ui-primary-rgb))] shrink-0 mt-0.5')
                                <p class="text-xs text-[var(--ui-secondary)] leading-relaxed">
                                    Die richtige Server-Adresse steckt bereits in der heruntergeladenen App – kein Eintippen nötig.
                                    Nach einem Neustart startet sie von selbst; mit der <strong>MENU-Taste</strong> der Fernbedienung
                                    lässt sich die Adresse bei Bedarf ändern.
                                </p>
                            </div>

                            {{-- Ausführliche Anleitung: Installation per Downloader-App --}}
                            <div x-data="{ dl: false }" class="mt-4 rounded-xl border border-[var(--ui-border)]/50">
                                <button type="button" x-on:click="dl = !dl"
                                        class="w-full flex items-center justify-between gap-2 px-4 py-3 text-left">
                                    <span class="flex items-center gap-2 text-sm font-semibold text-[var(--ui-secondary)]">
                                        @svg('heroicon-o-arrow-down-tray', 'w-4 h-4 text-[rgb(var(--ui-primary-rgb))]')
                                        Installation per Downloader-App – Schritt für Schritt
                                    </span>
                                    @svg('heroicon-o-chevron-down', 'w-4 h-4 text-[var(--ui-muted)] transition-transform shrink-0')
                                </button>

                                <div x-show="dl" x-cloak class="border-t border-[var(--ui-border)]/50 px-4 py-4">
                                    <ol class="space-y-3">
                                        <li class="flex gap-3">
                                            <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-indigo-100 text-indigo-600 text-xs font-bold">1</span>
                                            <div>
                                                <div class="text-sm font-semibold text-[var(--ui-secondary)]">Downloader-App installieren</div>
                                                <p class="mt-0.5 text-xs text-[var(--ui-muted)] leading-relaxed">
                                                    Am Fire TV oben die <strong>Suche</strong> (Lupe) öffnen, <strong>„Downloader"</strong> eingeben
                                                    und die orange App (von AFTVnews) installieren.
                                                </p>
                                            </div>
                                        </li>
                                        <li class="flex gap-3">
                                            <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-indigo-100 text-indigo-600 text-xs font-bold">2</span>
                                            <div>
                                                <div class="text-sm font-semibold text-[var(--ui-secondary)]">Entwickleroptionen freischalten</div>
                                                <p class="mt-0.5 text-xs text-[var(--ui-muted)] leading-relaxed">
                                                    <em>Einstellungen → Mein Fire TV → Info</em> öffnen und dort <strong>7× hintereinander</strong>
                                                    auf den Gerätenamen (z. B. „Fire TV Stick") klicken, bis <em>„Du bist jetzt Entwickler"</em>
                                                    erscheint. Bei älteren Geräten sind die Entwickleroptionen bereits direkt sichtbar.
                                                </p>
                                            </div>
                                        </li>
                                        <li class="flex gap-3">
                                            <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-indigo-100 text-indigo-600 text-xs font-bold">3</span>
                                            <div>
                                                <div class="text-sm font-semibold text-[var(--ui-secondary)]">Installation aus unbekannten Quellen erlauben</div>
                                                <p class="mt-0.5 text-xs text-[var(--ui-muted)] leading-relaxed">
                                                    <em>Einstellungen → Mein Fire TV → Entwickleroptionen → „Apps unbekannter Herkunft installieren"</em>
                                                    öffnen, in der Liste <strong>Downloader</strong> auswählen und einschalten.
                                                </p>
                                            </div>
                                        </li>
                                        <li class="flex gap-3">
                                            <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-indigo-100 text-indigo-600 text-xs font-bold">4</span>
                                            <div>
                                                <div class="text-sm font-semibold text-[var(--ui-secondary)]">Adresse in Downloader eingeben</div>
                                                <p class="mt-0.5 text-xs text-[var(--ui-muted)] leading-relaxed">
                                                    Downloader öffnen, im Feld <em>„URL eingeben"</em> die folgende Adresse eintippen und auf
                                                    <strong>„Go/Los"</strong> bestätigen:
                                                </p>
                                                <div class="mt-1.5 flex items-center gap-1" x-data="{ copied: false }">
                                                    <code class="flex-1 min-w-0 overflow-x-auto whitespace-nowrap select-all rounded bg-[var(--ui-muted-5)] px-2 py-1 text-[10px] text-[var(--ui-secondary)]">{{ url('/signage/firetv/app.apk') }}</code>
                                                    <button type="button"
                                                            x-on:click="navigator.clipboard.writeText('{{ url('/signage/firetv/app.apk') }}'); copied = true; setTimeout(() => copied = false, 1500)"
                                                            x-bind:title="copied ? 'Kopiert!' : 'Adresse kopieren'"
                                                            class="shrink-0 p-1.5 rounded border border-[var(--ui-border)] text-[var(--ui-muted)] hover:text-[rgb(var(--ui-primary-rgb))] hover:border-[rgb(var(--ui-primary-rgb))] transition">
                                                        <span x-show="!copied">@svg('heroicon-o-clipboard-document', 'w-4 h-4')</span>
                                                        <span x-show="copied" x-cloak>@svg('heroicon-o-check', 'w-4 h-4 text-green-600')</span>
                                                    </button>
                                                </div>
                                            </div>
                                        </li>
                                        <li class="flex gap-3">
                                            <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-indigo-100 text-indigo-600 text-xs font-bold">5</span>
                                            <div>
                                                <div class="text-sm font-semibold text-[var(--ui-secondary)]">Herunterladen & installieren</div>
                                                <p class="mt-0.5 text-xs text-[var(--ui-muted)] leading-relaxed">
                                                    Downloader lädt die APK und fragt anschließend nach der Installation → <strong>„Installieren"</strong>.
                                                    Nach dem Abschluss die heruntergeladene Datei <strong>löschen</strong> (Downloader bietet das an),
                                                    um Speicher zu sparen.
                                                </p>
                                            </div>
                                        </li>
                                        <li class="flex gap-3">
                                            <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-indigo-100 text-indigo-600 text-xs font-bold">6</span>
                                            <div>
                                                <div class="text-sm font-semibold text-[var(--ui-secondary)]">Öffnen & koppeln</div>
                                                <p class="mt-0.5 text-xs text-[var(--ui-muted)] leading-relaxed">
                                                    <strong>Digital Signage</strong> öffnen – es erscheint der Kopplungs-Code. Diesen oben über
                                                    <strong>Bildschirm koppeln</strong> eintragen. Fertig.
                                                </p>
                                            </div>
                                        </li>
                                    </ol>
                                </div>
                            </div>
                        </div>
                    </x-signage-panel>
                </div>
            @endif
        </div>

        {{-- Dialog: Bildschirm koppeln --}}
        @if($showCreateModal)
            <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
                 wire:click.self="closeCreateModal" wire:key="screen-create-modal">
                <div class="bg-[var(--ui-bg,#fff)] rounded-xl shadow-2xl w-full max-w-lg">
                    <div class="flex items-center justify-between px-5 py-3 border-b border-[var(--ui-border)]/40">
                        <h3 class="font-semibold text-[var(--ui-secondary)]">Bildschirm koppeln</h3>
                        <button wire:click="closeCreateModal" class="p-1 rounded text-[var(--ui-muted)] hover:text-[var(--ui-secondary)]">
                            @svg('heroicon-o-x-mark', 'w-5 h-5')
                        </button>
                    </div>
                    <form wire:submit="pair" class="space-y-4 p-5">
                        <p class="text-xs text-[var(--ui-muted)]">Öffne <span class="font-medium">{{ url('/signage/play') }}</span> am TV und gib den dort angezeigten Code ein.</p>
                        <div>
                            <x-ui-input-text name="pairingCode" label="Kopplungs-Code" wire:model="pairingCode" placeholder="z.B. K7P2QM" />
                            @error('pairingCode')<span class="text-xs text-red-600">{{ $message }}</span>@enderror
                        </div>
                        <x-ui-input-text name="pairingName" label="Name (optional)" wire:model="pairingName" placeholder="z.B. Empfang Foyer" />
                        <div class="flex items-center justify-end gap-2 pt-1">
                            <x-ui-button type="button" variant="secondary" wire:click="closeCreateModal">Abbrechen</x-ui-button>
                            <x-ui-button type="submit" variant="primary">Koppeln</x-ui-button>
                        </div>
                    </form>
                </div>
            </div>
        @endif
    </x-ui-page-container>
</x-ui-page>
