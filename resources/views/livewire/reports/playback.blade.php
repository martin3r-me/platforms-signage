<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="Wiedergabe-Statistik" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'Digital Signage', 'href' => route('signage.dashboard'), 'icon' => 'tv'],
            ['label' => 'Statistik'],
        ]" />
    </x-slot>

    <x-ui-page-container>
        <div class="space-y-4 pt-4">
            <x-signage-panel color="sky" icon="chart-bar" title="Wiedergaben je Medium" subtitle="Proof-of-Play – was lief wie oft">
                <div class="p-4 space-y-3">
                    <div class="flex items-center gap-2">
                        <label class="text-sm text-[var(--ui-muted)]">Zeitraum</label>
                        <select wire:model.live="days" class="px-2 py-1.5 text-sm rounded-lg border border-[var(--ui-border)] bg-white">
                            <option value="1">Heute</option>
                            <option value="7">7 Tage</option>
                            <option value="30">30 Tage</option>
                        </select>
                        <span class="text-sm text-[var(--ui-muted)] ml-auto">{{ number_format($total, 0, ',', '.') }} Wiedergaben gesamt</span>
                    </div>

                    @if($rows->isEmpty())
                        <div class="py-8 text-center text-[var(--ui-muted)] text-sm">
                            Noch keine Wiedergabe-Daten. Sobald Bildschirme spielen, erscheinen hier die Zahlen.
                        </div>
                    @else
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm">
                                <thead>
                                    <tr class="text-left text-[var(--ui-muted)] border-b border-[var(--ui-border)]/40">
                                        <th class="py-2 pr-3 font-medium">Medium</th>
                                        <th class="py-2 px-3 font-medium text-right">Wiedergaben</th>
                                        <th class="py-2 pl-3 font-medium text-right">Bildschirme</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-[var(--ui-border)]/40">
                                    @foreach($rows as $row)
                                        <tr wire:key="pop-{{ $row->media_id }}">
                                            <td class="py-2 pr-3 text-[var(--ui-secondary)]">{{ $names[$row->media_id] ?? 'Gelöschtes Medium' }}</td>
                                            <td class="py-2 px-3 text-right text-[var(--ui-secondary)]">{{ number_format($row->plays, 0, ',', '.') }}</td>
                                            <td class="py-2 pl-3 text-right text-[var(--ui-muted)]">{{ $row->screens }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </x-signage-panel>
        </div>
    </x-ui-page-container>
</x-ui-page>
