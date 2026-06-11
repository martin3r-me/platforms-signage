{{-- Freischalt-Baustein für den Fire-TV-Download per Code-Link.
     Erwartet aus dem Eltern-Scope: $apkCode, $apkCodeUrl, $apkCodeTtl.
     Aktionen (generateApkCode) laufen im Livewire-Scope der Screens\Index. --}}
<div x-data="{ copied: false }">
    @if($apkCode)
        <div class="rounded-lg border border-[rgb(var(--ui-primary-rgb))]/25 bg-[rgb(var(--ui-primary-rgb))]/5 p-3 space-y-2">
            <div class="flex items-center justify-between gap-2">
                <span class="text-[10px] uppercase tracking-wide text-[var(--ui-muted)]">Adresse für Downloader</span>
                <button type="button" wire:click="generateApkCode"
                        class="text-[10px] text-[rgb(var(--ui-primary-rgb))] hover:underline inline-flex items-center gap-1">
                    @svg('heroicon-o-arrow-path', 'w-3 h-3') Neuer Code
                </button>
            </div>
            <div class="flex items-center gap-1">
                <code class="flex-1 min-w-0 overflow-x-auto whitespace-nowrap select-all rounded bg-[var(--ui-muted-5)] px-2 py-1 text-[11px] text-[var(--ui-secondary)]">{{ $apkCodeUrl }}</code>
                <button type="button"
                        x-on:click="navigator.clipboard.writeText('{{ $apkCodeUrl }}'); copied = true; setTimeout(() => copied = false, 1500)"
                        x-bind:title="copied ? 'Kopiert!' : 'Adresse kopieren'"
                        class="shrink-0 p-1.5 rounded border border-[var(--ui-border)] text-[var(--ui-muted)] hover:text-[rgb(var(--ui-primary-rgb))] hover:border-[rgb(var(--ui-primary-rgb))] transition">
                    <span x-show="!copied">@svg('heroicon-o-clipboard-document', 'w-4 h-4')</span>
                    <span x-show="copied" x-cloak>@svg('heroicon-o-check', 'w-4 h-4 text-green-600')</span>
                </button>
            </div>
            <p class="text-[10px] text-[var(--ui-muted)] leading-relaxed">
                Code <strong class="tracking-[0.2em] text-[var(--ui-secondary)]">{{ $apkCode }}</strong> –
                gültig für {{ $apkCodeTtl }} Minuten. Danach einfach „Neuer Code".
            </p>
        </div>
    @else
        <button type="button" wire:click="generateApkCode" wire:loading.attr="disabled"
                class="inline-flex items-center gap-2 rounded-lg border border-[rgb(var(--ui-primary-rgb))]/40 bg-[rgb(var(--ui-primary-rgb))]/5 px-3 py-1.5 text-xs font-medium text-[rgb(var(--ui-primary-rgb))] hover:bg-[rgb(var(--ui-primary-rgb))]/10 transition">
            @svg('heroicon-o-key', 'w-4 h-4')
            Download-Link freischalten
        </button>
        <p class="mt-1 text-[10px] text-[var(--ui-muted)] leading-relaxed">
            Erzeugt einen 4-stelligen Code und eine Adresse, die du in der Downloader-App eingibst.
        </p>
    @endif
</div>
