<?php

namespace Platform\Signage\Livewire\Apps;

use Livewire\Component;
use Platform\Signage\Livewire\Concerns\WithCurrentTeam;
use Platform\Signage\Models\SignageMedia;
use Platform\Signage\Models\SignageScreen;
use Platform\Signage\Support\FleetBoardService;

/**
 * Editor für die Fahrzeug-Karte-App (create + edit). Zeigt im Player die Live-GPS-
 * Standorte der Fahrzeuge (DedeFleet TrackingObject/ListCurrentData) auf einer Karte.
 * Speichert als signage_media mit kind=app, app_type=fleetmap; Daten kommen zur
 * Laufzeit per Endpoint (wie die Tourenplan-App). Kein Token-Handling hier.
 */
class FleetMap extends Component
{
    use WithCurrentTeam;

    public ?int $mediaId = null;
    public string $name = '';

    public const STYLES = ['elegant', 'warm', 'modern', 'night'];

    public array $config = [
        'connection_id' => null,          // gewählte DedeFleet-Connection
        'style'         => 'modern',      // elegant | warm | modern | night (bestimmt hell/dunkle Karte)
        'title'         => 'Fahrzeuge',
        'show_clock'    => true,          // Uhr im Kopf
        'show_temp'     => false,         // Kühlketten-Temperatur am Marker
    ];

    public function mount(?SignageMedia $media = null): void
    {
        if ($media && $media->exists) {
            abort_unless($media->team_id === $this->teamId() && $media->isApp(), 403);

            $cfg = $media->config ?? [];
            $this->mediaId = $media->id;
            $this->name = (string) $media->name;
            $this->config = [
                'connection_id' => isset($cfg['connection_id']) ? (int) $cfg['connection_id'] : null,
                'style'         => in_array($cfg['style'] ?? '', self::STYLES, true) ? $cfg['style'] : 'modern',
                'title'         => (string) ($cfg['title'] ?? 'Fahrzeuge'),
                'show_clock'    => (bool) ($cfg['show_clock'] ?? true),
                'show_temp'     => (bool) ($cfg['show_temp'] ?? false),
            ];
        }
    }

    protected function rules(): array
    {
        return ['name' => 'required|string|max:255'];
    }

    public function save()
    {
        $this->validate();

        $config = [
            'connection_id' => $this->config['connection_id'] ? (int) $this->config['connection_id'] : null,
            'style'         => in_array($this->config['style'] ?? '', self::STYLES, true) ? $this->config['style'] : 'modern',
            'title'         => mb_substr((string) ($this->config['title'] ?? ''), 0, 120),
            'show_clock'    => (bool) ($this->config['show_clock'] ?? true),
            'show_temp'     => (bool) ($this->config['show_temp'] ?? false),
        ];

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
                'app_type'          => 'fleetmap',
                'config'            => $config,
                'processing_status' => 'ready',
            ]);
        }

        session()->flash('signage_message', 'Fahrzeug-Karte gespeichert.');

        return $this->redirectRoute('signage.media.index', navigate: true);
    }

    public function render()
    {
        return view('signage::livewire.apps.fleetmap', [
            'connections'        => FleetBoardService::connectionsForUser(auth()->user()),
            'integrationPresent' => FleetBoardService::integrationPresent(),
            'liveAvailable'      => FleetBoardService::available(),
            'dataEndpoint'       => route('signage.apps.fleetmap.data'),
        ])->layout('platform::layouts.app');
    }
}
