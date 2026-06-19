{{-- SortableJS-Anbindung (ohne Alpine). Container mit [data-sortable-action] werden
     initialisiert; beim Loslassen wird die Reihenfolge der [data-sortable-id]-Kinder
     an die Livewire-Methode übergeben. @assets hält das Script über wire:navigate. --}}
@assets
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js"></script>
<style>
.signage-sortable-ghost { opacity: .35 !important; background: var(--ui-muted-5, #f3f4f6) !important; }
.signage-sortable-fallback { opacity: .95 !important; box-shadow: 0 12px 28px rgba(0,0,0,.18); }
</style>
<script>
(function () {
    if (window._signageSortableBooted) return;
    window._signageSortableBooted = true;
    const KEY = '__signageSortableInstance';

    function initOne(el) {
        if (!window.Sortable || el[KEY]) return el[KEY] ? true : false;
        const action = el.getAttribute('data-sortable-action');
        if (!action) return false;

        el[KEY] = window.Sortable.create(el, {
            animation: 150,
            handle: '.js-drag-handle',
            filter: 'input,textarea,select,button,a',
            preventOnFilter: false,
            forceFallback: true,
            fallbackOnBody: true,
            fallbackTolerance: 3,
            ghostClass: 'signage-sortable-ghost',
            fallbackClass: 'signage-sortable-fallback',
            onEnd: function (evt) {
                if (evt.oldIndex === evt.newIndex) return;
                const ids = Array.from(el.querySelectorAll('[data-sortable-id]'))
                    .map(function (n) { return n.dataset.sortableId; });

                let wireEl = el;
                while (wireEl && !(wireEl.hasAttribute && wireEl.hasAttribute('wire:id'))) {
                    wireEl = wireEl.parentElement;
                }
                if (!wireEl || !window.Livewire) return;
                const component = window.Livewire.find(wireEl.getAttribute('wire:id'));
                if (component && typeof component.call === 'function') {
                    component.call(action, ids);
                }
            },
        });
        return true;
    }

    function initAll(root) {
        (root || document).querySelectorAll('[data-sortable-action]').forEach(initOne);
    }

    (function waitForSortable(attempts) {
        if (window.Sortable) return initAll();
        if (attempts <= 0) return console.warn('[signageSortable] SortableJS nicht geladen');
        setTimeout(function () { waitForSortable(attempts - 1); }, 50);
    })(200);

    document.addEventListener('DOMContentLoaded', function () { initAll(); });
    document.addEventListener('livewire:navigated', function () { initAll(); });
    document.addEventListener('livewire:initialized', function () {
        initAll();
        if (window.Livewire && typeof window.Livewire.hook === 'function') {
            window.Livewire.hook('morph.updated', function () { initAll(); });
        }
    });
})();
</script>
@endassets
