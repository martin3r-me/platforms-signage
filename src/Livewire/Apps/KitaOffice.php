<?php

namespace Platform\Signage\Livewire\Apps;

use Livewire\Attributes\Locked;
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

    /** Kalender-Auswahl: M365-Gruppen des Kontos hinter der gewählten Verbindung. */
    #[Locked]
    public array $m365Groups = [];
    #[Locked]
    public ?string $m365GroupsError = null;

    /** SharePoint-Auswahl: target (menu|phone), Pfad (trail) und Einträge der aktuellen Ebene. */
    #[Locked]
    public array $picker = ['target' => null, 'trail' => [], 'entries' => [], 'error' => null];

    /** Ergebnis der Sofort-Prüfung je Quelle (menu|phone). */
    #[Locked]
    public array $probe = ['menu' => null, 'phone' => null];

    private const PICK_FIELDS = ['menu' => 'menu_folder_item', 'phone' => 'phone_list_item'];

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
        $fields = ['m365_connection_id', 'calendar_group_id', 'menu_folder_url', 'menu_folder_item', 'menu_file_pattern', 'phone_list_url', 'phone_list_item'];
        $unchanged = $previous !== [] && array_intersect_key($previous, array_flip($fields)) == array_intersect_key($config, array_flip($fields));
        $previousUser = (int) (($media->config ?? [])['m365_user_id'] ?? 0);

        if ($unchanged && $previousUser) {
            return $previousUser;
        }

        $this->addError('config.m365_connection_id', 'Diese Microsoft-365-Verbindung steht dir nicht zur Verfügung – bitte eine eigene auswählen.');

        return null;
    }

    // ------------------------------------------------------------------
    // Microsoft 365: Auswahl statt IDs/Links. Alle Abrufe laufen als angemeldeter User und
    // nur mit einer Verbindung, die er selbst nutzen darf.
    // ------------------------------------------------------------------

    /** @return array{0: \Platform\Core\Models\User, 1: int}|null */
    private function m365Context(): ?array
    {
        $user = auth()->user();
        $connectionId = KitaOfficeConfig::sanitize($this->config)['m365_connection_id'];

        return $connectionId && M365Source::available() && M365Source::canUse($user, $connectionId)
            ? [$user, $connectionId]
            : null;
    }

    private function m365ContextError(): string
    {
        return ($this->config['m365_connection_id'] ?? null)
            ? 'Mit dieser Verbindung kannst du nicht auswählen – sie gehört einem anderen Konto. Bitte deine eigene auswählen.'
            : 'Bitte zuerst eine Microsoft-365-Verbindung auswählen.';
    }

    public function loadGroups(): void
    {
        $this->m365Groups = [];
        $this->m365GroupsError = null;
        $this->probe = ['menu' => null, 'phone' => null];

        if (!$ctx = $this->m365Context()) {
            $this->m365GroupsError = ($this->config['m365_connection_id'] ?? null) ? $this->m365ContextError() : null;

            return;
        }

        try {
            $this->m365Groups = M365Source::groups(...$ctx);
        } catch (\Throwable $e) {
            $this->m365GroupsError = M365Source::editorError($e);
        }
    }

    public function openPicker(string $target): void
    {
        abort_unless(isset(self::PICK_FIELDS[$target]), 404);

        $this->picker = ['target' => $target, 'trail' => [['label' => 'Gruppen & Websites', 'at' => []]], 'entries' => [], 'error' => null];
        $this->loadPickerLevel();
    }

    public function closePicker(): void
    {
        $this->picker = ['target' => null, 'trail' => [], 'entries' => [], 'error' => null];
    }

    /** Eintrag der aktuellen Ebene öffnen (Gruppe/Website/Bibliothek/Ordner) bzw. Datei wählen. */
    public function pickerOpen(int $index): void
    {
        $entry = $this->picker['entries'][$index] ?? null;
        if (!$entry || !$this->picker['target']) {
            return;
        }

        if ($entry['type'] === 'file') {
            $this->choose($entry['drive_id'], $entry['item_id'], $entry['name']);

            return;
        }

        $at = in_array($entry['type'], ['group', 'site'], true)
            ? ['type' => $entry['type'], 'id' => $entry['id']]
            : ['type' => 'folder', 'drive_id' => $entry['drive_id'], 'item_id' => $entry['item_id']];
        $this->picker['trail'][] = ['label' => $entry['name'], 'at' => $at];
        $this->loadPickerLevel();
    }

    /** Im Pfad zurück auf Ebene $level (0 = Start). */
    public function pickerBack(int $level): void
    {
        if (!$this->picker['target']) {
            return;
        }
        $this->picker['trail'] = array_slice($this->picker['trail'], 0, max(1, $level + 1));
        $this->loadPickerLevel();
    }

    /** Speiseplan: den aktuell geöffneten Ordner übernehmen. */
    public function pickerChooseFolder(): void
    {
        $at = end($this->picker['trail'])['at'] ?? [];
        if ($this->picker['target'] !== 'menu' || ($at['type'] ?? '') !== 'folder') {
            return;
        }
        $this->choose($at['drive_id'], $at['item_id'], null);
    }

    public function clearSource(string $target): void
    {
        abort_unless(isset(self::PICK_FIELDS[$target]), 404);

        $this->config[self::PICK_FIELDS[$target]] = null;
        $this->probe[$target] = null;
        $this->dispatch('kitaoffice-config-updated');
    }

    /** Sofort-Prüfung: Speiseplan für heute auffindbar bzw. Telefonliste lesbar? */
    public function probeSource(string $target): void
    {
        abort_unless(isset(self::PICK_FIELDS[$target]), 404);

        $config = KitaOfficeConfig::sanitize($this->config);
        $source = $target === 'menu'
            ? KitaOfficeConfig::source($config, 'menu_folder_item', 'menu_folder_url')
            : KitaOfficeConfig::source($config, 'phone_list_item', 'phone_list_url');

        if ($source === null) {
            $this->probe[$target] = null;

            return;
        }
        if (!$ctx = $this->m365Context()) {
            $this->probe[$target] = $this->m365ContextError();

            return;
        }

        $this->probe[$target] = $target === 'menu'
            ? M365Source::probeMenu($ctx[0], $ctx[1], $source, $config['menu_file_pattern'])
            : M365Source::probeContacts($ctx[0], $ctx[1], $source);
    }

    private function choose(string $driveId, string $itemId, ?string $fileName): void
    {
        $target = $this->picker['target'];
        $labels = array_column(array_slice($this->picker['trail'], 1), 'label');
        if ($fileName !== null) {
            $labels[] = $fileName;
        }

        $item = KitaOfficeConfig::driveItem([
            'drive_id' => $driveId,
            'item_id'  => $itemId,
            'label'    => implode(' › ', $labels),
            'kind'     => $fileName !== null ? 'file' : 'folder',
        ]);
        if (!$item) {
            $this->picker['error'] = 'Auswahl konnte nicht übernommen werden.';

            return;
        }

        $this->config[self::PICK_FIELDS[$target]] = $item;
        $this->closePicker();
        $this->probeSource($target);
        $this->dispatch('kitaoffice-config-updated');
    }

    private function loadPickerLevel(): void
    {
        $this->picker['entries'] = [];
        $this->picker['error'] = null;

        if (!$ctx = $this->m365Context()) {
            $this->picker['error'] = $this->m365ContextError();

            return;
        }

        $at = end($this->picker['trail'])['at'] ?? [];
        try {
            $this->picker['entries'] = M365Source::browse($ctx[0], $ctx[1], $at, $this->picker['target'] === 'phone' ? '.xlsx' : '.docx');
        } catch (\Throwable $e) {
            $this->picker['error'] = M365Source::editorError($e);
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

        // Wer liefert am Bildschirm aktuell die M365-Daten (gespeicherter Stand)?
        $dataUserId = (int) (($media?->config ?? [])['m365_user_id'] ?? 0);
        $m365DataUser = $dataUserId ? (\Platform\Core\Models\User::find($dataUserId)?->name ?? 'unbekannt') : null;

        return view('signage::livewire.apps.kitaoffice', [
            'm365Connections'        => $m365Connections,
            'm365DataUser'           => $m365DataUser,
            'm365SelfName'           => (string) ($user?->name ?? ''),
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
