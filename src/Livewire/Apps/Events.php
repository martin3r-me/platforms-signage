<?php

namespace Platform\Signage\Livewire\Apps;

use Livewire\Component;
use Platform\Signage\Livewire\Concerns\WithCurrentTeam;
use Platform\Signage\Models\SignageMedia;
use Platform\Signage\Models\SignageScreen;
use Platform\Signage\Support\EventBoardService;

/**
 * Editor für die Veranstaltungs-/Belegungs-App (create + edit). Zeigt im Player
 * die heutigen/kommenden Buchungen aus dem Events-Modul. Speichert als signage_media
 * mit kind=app, app_type=events; die Daten kommen zur Laufzeit per Endpoint.
 */
class Events extends Component
{
    use WithCurrentTeam;

    public ?int $mediaId = null;
    public string $name = '';

    public const STYLES = ['elegant', 'warm', 'modern', 'night'];

    public array $config = [
        'style'  => 'elegant',                   // elegant | warm | modern | night
        'title'  => 'Heutige Veranstaltungen',
        'days'   => 1,                           // 1..14
        'status' => [],                          // leer = alle, sonst z.B. ['Definitiv','Vertrag']
    ];

    public function mount(?SignageMedia $media = null): void
    {
        if ($media && $media->exists) {
            abort_unless($media->team_id === $this->teamId() && $media->isApp(), 403);

            $cfg = $media->config ?? [];
            $this->mediaId = $media->id;
            $this->name = (string) $media->name;
            $this->config = [
                'style'  => in_array($cfg['style'] ?? '', self::STYLES, true)
                                ? $cfg['style']
                                : (($cfg['theme'] ?? 'dark') === 'light' ? 'warm' : 'elegant'),
                'title'  => (string) ($cfg['title'] ?? 'Heutige Veranstaltungen'),
                'days'   => max(1, min(14, (int) ($cfg['days'] ?? 1))),
                'status' => is_array($cfg['status'] ?? null) ? array_values($cfg['status']) : [],
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

        $allowed = EventBoardService::knownStatuses();
        $config = [
            'style'  => in_array($this->config['style'] ?? '', self::STYLES, true) ? $this->config['style'] : 'elegant',
            'title'  => mb_substr((string) ($this->config['title'] ?? ''), 0, 120),
            'days'   => max(1, min(14, (int) ($this->config['days'] ?? 1))),
            'status' => array_values(array_intersect(
                is_array($this->config['status'] ?? null) ? $this->config['status'] : [],
                $allowed
            )),
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
                'app_type'          => 'events',
                'config'            => $config,
                'processing_status' => 'ready',
            ]);
        }

        session()->flash('signage_message', 'Veranstaltungs-App gespeichert.');

        return $this->redirectRoute('signage.media.index', navigate: true);
    }

    public function render()
    {
        return view('signage::livewire.apps.events', [
            'statuses'        => EventBoardService::knownStatuses(),
            'eventsAvailable' => EventBoardService::available(),
            'dataEndpoint'    => route('signage.apps.events.data'),
        ])->layout('platform::layouts.app');
    }
}
