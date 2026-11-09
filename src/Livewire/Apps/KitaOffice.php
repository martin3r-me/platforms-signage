<?php

namespace Platform\Signage\Livewire\Apps;

use Livewire\Component;
use Platform\Signage\Livewire\Concerns\WithCurrentTeam;
use Platform\Signage\Models\SignageMedia;
use Platform\Signage\Models\SignageScreen;
use Platform\Signage\Support\FleetBoardService;
use Platform\Signage\Support\KitaOffice\KitaOfficeConfig;
use Platform\Signage\Support\KitaOfficeBoardService;

/**
 * Editor für die Kita-Office-App (create + edit): Kopf mit Datum/KW/Wetter/Uhr,
 * CoolCooking-Boxen (MeinConnect) und Touren mit Fahrer-Telefon (DedeFleet).
 * Speichert als signage_media mit kind=app, app_type=kitaoffice.
 *
 * Zugangsdaten liegen ausschließlich in den Connections von platforms-integrations;
 * hier werden nur Connections ausgewählt.
 */
class KitaOffice extends Component
{
    use WithCurrentTeam;

    public ?int $mediaId = null;
    public string $name = '';

    public array $config = [];

    public function mount(?SignageMedia $media = null): void
    {
        $this->config = KitaOfficeConfig::defaults();

        if ($media && $media->exists) {
            abort_unless($media->team_id === $this->teamId() && $media->isApp(), 403);

            $this->mediaId = $media->id;
            $this->name = (string) $media->name;
            $this->config = KitaOfficeConfig::sanitize(($media->config ?? []) + KitaOfficeConfig::defaults());
        }
    }

    protected function rules(): array
    {
        return ['name' => 'required|string|max:255'];
    }

    public function save()
    {
        $this->validate();

        $config = KitaOfficeConfig::sanitize($this->config);

        if ($this->mediaId) {
            $media = SignageMedia::where('team_id', $this->teamId())->findOrFail($this->mediaId);
            $media->update(['name' => $this->name, 'config' => $config]);
            SignageScreen::bumpForMedia($media->id);
        } else {
            SignageMedia::create([
                'team_id'           => $this->teamId(),
                'user_id'           => auth()->id(),
                'name'              => $this->name,
                'kind'              => 'app',
                'app_type'          => 'kitaoffice',
                'config'            => $config,
                'processing_status' => 'ready',
            ]);
        }

        session()->flash('signage_message', 'Kita-Office-App gespeichert.');

        return $this->redirectRoute('signage.media.index', navigate: true);
    }

    public function render()
    {
        $user = auth()->user();

        // Bilder für die Logo-Auswahl (+ signierte URLs für die Live-Vorschau).
        $manifest = app(\Platform\Signage\Services\PlayerManifestService::class);
        $images = SignageMedia::where('team_id', $this->teamId())
            ->where('kind', 'image')
            ->orderBy('name')
            ->limit(100)
            ->get();

        return view('signage::livewire.apps.kitaoffice', [
            'meinconnectConnections' => KitaOfficeBoardService::meinconnectConnectionsForUser($user),
            'meinconnectPresent'     => KitaOfficeBoardService::meinconnectAvailable(),
            'dedefleetConnections'   => FleetBoardService::connectionsForUser($user),
            'dedefleetPresent'       => FleetBoardService::integrationPresent(),
            'dataEndpoint'           => route('signage.apps.kitaoffice.data'),
            'logoImages'             => $images->map(fn ($m) => ['id' => $m->id, 'name' => (string) $m->name])->all(),
            'logoUrls'               => $images->mapWithKeys(fn ($m) => [$m->id => $manifest->mediaUrl($m)])->all(),
        ])->layout('platform::layouts.app');
    }
}
