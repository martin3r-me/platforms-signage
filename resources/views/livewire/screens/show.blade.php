@php
    $visualOptions = $visualPlaylists->map(fn($p) => ['value' => $p->id, 'label' => $p->name])->values()->all();
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
        <div class="space-y-6 pt-4">
            @if(session('signage_message'))
                <div class="p-3 rounded bg-green-100 text-green-800 text-sm">{{ session('signage_message') }}</div>
            @endif

            <x-signage-panel icon="eye" title="Live-Vorschau">
                <div class="p-4">
                    <div class="relative w-full max-w-2xl mx-auto rounded-lg overflow-hidden border border-[var(--ui-border)]/40 bg-black {{ str_starts_with($screen->orientation, 'portrait') ? 'aspect-[9/16] max-w-xs' : 'aspect-video' }}">
                        <iframe src="{{ $previewUrl }}" class="absolute inset-0 w-full h-full" frameborder="0"></iframe>
                    </div>
                </div>
            </x-signage-panel>

            <x-signage-panel icon="cog-6-tooth" title="Einstellungen">
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
                        {{-- Native Select: lange Zeitzonen-Liste, OS-Dropdown wird nicht vom Panel abgeschnitten. --}}
                        <div>
                            <label class="block text-sm font-medium text-[var(--ui-secondary)] mb-1">Zeitzone (für Zeitpläne)</label>
                            <select wire:model="timezone"
                                    class="w-full px-3 py-2 rounded-lg border border-[var(--ui-border)] bg-white text-sm text-[var(--ui-secondary)]">
                                <option value="">– Standard –</option>
                                @foreach($timezoneOptions as $tz)
                                    <option value="{{ $tz['value'] }}">{{ $tz['label'] }}</option>
                                @endforeach
                            </select>
                        </div>
                        <x-ui-input-select name="musicSource" label="Hintergrundmusik" wire:model="musicSource"
                            :options="$musicOptions" optionValue="value" optionLabel="label" :nullable="true" nullLabel="– keine –" />
                    </div>

                    {{-- Zeitpläne: mehrere kombinierbar, müssen sich aber zeitlich ergänzen (keine Überschneidung). --}}
                    <div>
                        <label class="block text-sm font-medium text-[var(--ui-secondary)] mb-1">Zeitpläne</label>
                        @if(count($scheduleOptions))
                            <div class="space-y-1.5 rounded-lg border border-[var(--ui-border)] p-3 max-h-56 overflow-y-auto">
                                @foreach($scheduleOptions as $opt)
                                    <label class="flex items-center gap-2 text-sm cursor-pointer">
                                        <input type="checkbox" value="{{ $opt['value'] }}" wire:model="scheduleIds" class="rounded">
                                        <span class="text-[var(--ui-secondary)]">{{ $opt['label'] }}</span>
                                    </label>
                                @endforeach
                            </div>
                        @else
                            <p class="text-xs text-[var(--ui-muted)]">Noch keine Zeitpläne angelegt.</p>
                        @endif
                        @error('scheduleIds')
                            <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>
                        @enderror

                        {{-- Abdeckung: greifen die Pläne durchgehend oder gibt es Lücken? --}}
                        @if(count($scheduleIds))
                            <div class="mt-2 text-xs">
                                @if($coverage['full'])
                                    <span class="text-emerald-600">✅ Anzeige durchgehend abgedeckt (Mo–So).</span>
                                @else
                                    <div class="text-amber-600 font-medium">⚠️ Lücken (dort greift die Standard-Wiedergabeliste, sonst leer):</div>
                                    <div class="mt-1 flex flex-wrap gap-1.5">
                                        @foreach($coverage['labels'] as $g)
                                            <span class="rounded bg-amber-50 border border-amber-200 px-1.5 py-0.5 text-amber-700">{{ $g }}</span>
                                        @endforeach
                                    </div>
                                @endif
                            </div>
                        @endif
                    </div>

                    {{-- "Was läuft gerade?" – aktiver Zeitplan + Liste für die aktuelle Bildschirm-Zeit. --}}
                    @php
                        $fmtActive = function ($a) {
                            if (($a['source'] ?? 'none') === 'schedule') {
                                return '«'.$a['schedule'].'» → Liste «'.($a['playlist'] ?? '–').'»';
                            }
                            if (($a['source'] ?? 'none') === 'default') {
                                return 'Standard-Wiedergabeliste «'.($a['playlist'] ?? '–').'»';
                            }
                            return '— nichts —';
                        };
                    @endphp
                    <div>
                        <button type="button" wire:click="$toggle('showActive')"
                                class="inline-flex items-center gap-1.5 text-sm font-medium text-[var(--ui-primary)] hover:underline">
                            @svg('heroicon-o-play-circle', 'w-4 h-4')
                            Was läuft gerade?
                        </button>
                        @if($showActive)
                            <div class="mt-2 rounded-lg border border-[var(--ui-border)] bg-[var(--ui-muted-5)]/40 p-3 text-sm space-y-1.5">
                                <div class="text-xs text-[var(--ui-muted)]">
                                    Stand: {{ $activeNow['now'] }} Uhr ({{ $activeNow['tz'] }})
                                </div>
                                <div class="flex gap-2">
                                    <span class="shrink-0 font-medium text-[var(--ui-secondary)]">Anzeige:</span>
                                    <span class="text-[var(--ui-secondary)]">{{ $fmtActive($activeNow['visual']) }}</span>
                                </div>
                                <div class="flex gap-2">
                                    <span class="shrink-0 font-medium text-[var(--ui-secondary)]">Musik:</span>
                                    <span class="text-[var(--ui-secondary)]">{{ $fmtActive($activeNow['music']) }}</span>
                                </div>
                                @if(!empty($activeNow['next']))
                                    <div class="flex gap-2 pt-1.5 border-t border-[var(--ui-border)]/40 text-xs text-[var(--ui-muted)]">
                                        <span class="shrink-0 font-medium">Danach (Anzeige):</span>
                                        <span>ab {{ $activeNow['next']['at'] }} Uhr → {{ $fmtActive($activeNow['next']) }}</span>
                                    </div>
                                @endif
                            </div>
                        @endif
                    </div>

                    <p class="text-xs text-[var(--ui-muted)]">
                        Zugewiesene Zeitpläne übersteuern die Standard-Wiedergabeliste in ihren Zeitfenstern und greifen ineinander.
                        Mehrere Pläne dürfen sich zeitlich <strong>nicht überschneiden</strong> – sonst erscheint beim Speichern eine Fehlermeldung.
                        Zeitpläne werden unter
                        <a href="{{ route('signage.schedules.index') }}" wire:navigate class="text-[var(--ui-primary)] underline">Zeitpläne</a> verwaltet.
                    </p>
                    <x-ui-button type="submit" variant="primary">Speichern</x-ui-button>
                </form>
            </x-signage-panel>

        </div>
    </x-ui-page-container>
</x-ui-page>
