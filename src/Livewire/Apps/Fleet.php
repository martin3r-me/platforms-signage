<?php

namespace Platform\Signage\Livewire\Apps;

use Livewire\Component;
use Platform\Signage\Livewire\Concerns\WithCurrentTeam;
use Platform\Signage\Models\SignageMedia;
use Platform\Signage\Models\SignageScreen;
use Platform\Signage\Support\FleetBoardService;

/**
 * Editor für die DedeFleet-Tourenplan-App (create + edit). Zeigt im Player den
 * heutigen Tourenplan (Touren, Fahrer, Fahrzeug, Stopps) live aus DedeFleet.
 * Speichert als signage_media mit kind=app, app_type=dedefleet; die Daten kommen
 * zur Laufzeit per Endpoint (Muster wie die Veranstaltungs-App).
 *
 * Die eigentliche API-Anbindung liegt in platforms-integrations. Hier wird nur
 * eine vorhandene DedeFleet-Connection ausgewählt (kein Token-Handling).
 */
class Fleet extends Component
{
    use WithCurrentTeam;

    public ?int $mediaId = null;
    public string $name = '';

    public const STYLES = ['elegant', 'warm', 'modern', 'night'];

    public array $config = [
        'connection_id' => null,          // gewählte DedeFleet-Connection
        'style'         => 'modern',      // elegant | warm | modern | night
        'title'         => 'Tourenplan',
        'show_clock'    => true,          // große Uhr im Kopf
        'show_progress' => true,          // Fortschritt/erledigt je Stopp
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
                'title'         => (string) ($cfg['title'] ?? 'Tourenplan'),
                'show_clock'    => (bool) ($cfg['show_clock'] ?? true),
                'show_progress' => (bool) ($cfg['show_progress'] ?? true),
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
            'show_progress' => (bool) ($this->config['show_progress'] ?? true),
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
                'app_type'          => 'dedefleet',
                'config'            => $config,
                'processing_status' => 'ready',
            ]);
        }

        session()->flash('signage_message', 'Tourenplan-App gespeichert.');

        return $this->redirectRoute('signage.media.index', navigate: true);
    }

    public function render()
    {
        return view('signage::livewire.apps.fleet', [
            'connections'        => FleetBoardService::connectionsForUser(auth()->user()),
            'integrationPresent' => FleetBoardService::integrationPresent(),
            'liveAvailable'      => FleetBoardService::available(),
            'dataEndpoint'       => route('signage.apps.dedefleet.data'),
        ])->layout('platform::layouts.app');
    }
}
