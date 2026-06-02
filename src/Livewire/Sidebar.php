<?php

namespace Platform\Signage\Livewire;

use Livewire\Component;

/**
 * Modul-Sidebar (linke Navigation).
 *
 * Wird von der Plattform automatisch eingebunden: das App-Layout rendert
 * per Konvention `@livewire('signage.sidebar')`, sofern diese Klasse existiert.
 * Die `sidebar`-Config in config/signage.php ist nur Metadaten und wird NICHT
 * automatisch gerendert – die Navigation muss hier ausgegeben werden.
 */
class Sidebar extends Component
{
    public function render()
    {
        return view('signage::livewire.sidebar');
    }
}
