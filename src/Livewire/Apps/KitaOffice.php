<?php

namespace Platform\Signage\Livewire\Apps;

use Livewire\Component;
use Platform\Signage\Livewire\Concerns\WithCurrentTeam;
use Platform\Signage\Models\SignageMedia;
use Platform\Signage\Models\SignageScreen;
use Platform\Signage\Support\FleetBoardService;
use Platform\Signage\Support\KitaOffice\KitaOfficeConfig;
use Platform\Signage\Support\KitaOffice\M365Source;
use Platform\Signage\Support\KitaOfficeBoardService;

/**
 * Editor für die Kita-Office-App (create + edit): Kopf mit Datum/KW/Wetter/Uhr,
 * CoolCooking-Boxen (MeinConnect) und Touren mit Fahrer-Telefon (DedeFleet).
 * Speichert als signage_media mit kind=app, app_type=kitaoffice.
 *
 * Zugangsdaten liegen ausschließlich in den Connections von platforms-integrations bzw.
 * platform-user-connectors; hier werden nur Connections ausgewählt.
 *
 * Microsoft 365: Auswählbar sind nur Connections, auf die der angemeldete User selbst Zugriff
 * hat (eigene + geteilte). Beim Speichern wird er als m365_user_id hinterlegt; der Bildschirm
 * ruft Graph dann mit genau diesem User auf.
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

    private function media(): ?SignageMedia
    {
        return $this->mediaId
            ? SignageMedia::where('team_id', $this->teamId())->findOrFail($this->mediaId)
            : null;
    }

    /**
     * Wer liefert am Bildschirm die M365-Daten? Der Speichernde, sofern er die Connection
     * nutzen darf. Sonst bleibt der bisherige User nur, wenn an Connection und M365-Quellen
     * nichts geändert wurde (z. B. nur die Boxen-Einstellungen angepasst).
     */
    private function m365UserFor(array $config, ?SignageMedia $media): ?int
    {
        $connectionId = $config['m365_connection_id'];
        if (!$connectionId) {
            return null;
        }
        if (M365Source::canUse(auth()->user(), $connectionId)) {
            return (int) auth()->id();
        }

        $previous = $media ? KitaOfficeConfig::sanitize($media->config ?? []) : [];
        $fields = ['m365_connection_id', 'calendar_group_id', 'menu_folder_url', 'menu_file_pattern', 'phone_list_url'];
        $unchanged = $previous !== [] && array_intersect_key($previous, array_flip($fields)) == array_intersect_key($config, array_flip($fields));
        $previousUser = (int) (($media->config ?? [])['m365_user_id'] ?? 0);

        if ($unchanged && $previousUser) {
            return $previousUser;
        }

        $this->addError('config.m365_connection_id', 'Diese Microsoft-365-Verbindung steht dir nicht zur Verfügung – bitte eine eigene auswählen.');

        return null;
    }

    protected function rules(): array
    {
        return ['name' => 'required|string|max:255'];
    }

    public function save()
    {
        $this->validate();

        $config = KitaOfficeConfig::sanitize($this->config);
        $media = $this->media();
        $config['m365_user_id'] = $this->m365UserFor($config, $media);
        if ($this->getErrorBag()->isNotEmpty()) {
            return null;
        }

        if ($media) {
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

        // Bereits gespeicherte Connection eines anderen Users weiter anzeigen (beibehalten).
        $m365Connections = M365Source::connectionsForUser($user);
        $media = $this->mediaId ? SignageMedia::where('team_id', $this->teamId())->find($this->mediaId) : null;
        $savedConn = $this->config['m365_connection_id'] ?? null;
        if ($savedConn && !in_array((int) $savedConn, array_column($m365Connections, 'id'), true)) {
            $ownerId = (int) (($media?->config ?? [])['m365_user_id'] ?? 0);
            $owner = $ownerId ? \Platform\Core\Models\User::find($ownerId) : null;
            $m365Connections[] = ['id' => (int) $savedConn, 'label' => 'Verbindung von ' . ($owner?->name ?? 'unbekannt') . ' (beibehalten)'];
        }

        return view('signage::livewire.apps.kitaoffice', [
            'm365Connections'        => $m365Connections,
            'm365Present'            => M365Source::available(),
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
