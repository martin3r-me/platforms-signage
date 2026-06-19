<?php

namespace Platform\Signage\Livewire\Media;

use Illuminate\Http\UploadedFile;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;
use Platform\Signage\Livewire\Concerns\WithCurrentTeam;
use Platform\Signage\Models\SignageMedia;

class Index extends Component
{
    use WithCurrentTeam, WithFileUploads, WithPagination;

    /** @var array<UploadedFile> */
    public $uploads = [];

    // Ordner-Organisation
    public ?int $currentFolderId = null;
    public string $newFolderName = '';

    // Sortierung / Filter (Auswahl wird clientseitig in Alpine gehalten -> sofortiges Markieren)
    public string $sortBy = 'created_desc'; // created_desc | created_asc | name_asc | name_desc
    public string $filterKind = '';         // '' = alle | image|video|audio|document|app|website

    public function updatedSortBy(): void
    {
        $this->resetPage();
    }

    public function updatedFilterKind(): void
    {
        $this->resetPage();
    }

    /** Löscht die per Mehrfachauswahl übergebenen Medien (IDs aus dem Client). */
    public function bulkDeleteMedia(array $ids): void
    {
        $ids = array_filter(array_map('intval', $ids));
        if (empty($ids)) {
            return;
        }
        $items = SignageMedia::where('team_id', $this->teamId())->whereIn('id', $ids)->get();
        $items->each(fn ($m) => $m->delete());
        session()->flash('signage_message', $items->count().' Medium(e) gelöscht.');
    }

    public function rules(): array
    {
        $maxKb = (int) config('signage.max_upload_kb', 512000);

        return [
            'uploads.*' => 'file|max:'.$maxKb.'|mimes:jpg,jpeg,png,webp,gif,mp4,webm,mp3,aac,ogg,wav,pdf,ppt,pptx',
        ];
    }

    public function messages(): array
    {
        return [
            'uploads.*.max'   => 'Die Datei ist zu groß (max. '.$this->maxUploadLabel().').',
            'uploads.*.mimes' => 'Dieses Dateiformat wird nicht unterstützt.',
            'uploads.*.file'  => 'Die Datei konnte nicht hochgeladen werden.',
        ];
    }

    public function updatedUploads(): void
    {
        $this->validate();
        $this->saveUploads();
    }

    /**
     * Kleinste effektive Upload-Grenze aus PHP (upload_max_filesize, post_max_size)
     * und der App-Konfiguration – als menschenlesbares Label (z.B. "500 MB").
     */
    public function maxUploadLabel(): string
    {
        $toBytes = function (string $v): int {
            $v = trim($v);
            if ($v === '') {
                return 0;
            }
            $unit = strtolower($v[strlen($v) - 1]);
            $num = (int) $v;

            return match ($unit) {
                'g'     => $num * 1024 ** 3,
                'm'     => $num * 1024 ** 2,
                'k'     => $num * 1024,
                default => (int) $v,
            };
        };

        $candidates = array_filter([
            $toBytes((string) ini_get('upload_max_filesize')),
            $toBytes((string) ini_get('post_max_size')),
            ((int) config('signage.max_upload_kb', 512000)) * 1024,
        ]);

        $bytes = $candidates ? min($candidates) : 0;

        if ($bytes >= 1024 ** 3) {
            return round($bytes / 1024 ** 3, 1).' GB';
        }

        return round($bytes / 1024 ** 2).' MB';
    }

    protected function saveUploads(): void
    {
        $teamId = $this->teamId();
        $userId = auth()->id();
        $uploader = app(\Platform\Signage\Services\MediaUploadService::class);

        foreach ($this->uploads as $file) {
            if (!$file instanceof UploadedFile) {
                continue;
            }

            $uploader->store($file, $teamId, $userId, $this->currentFolderId);
        }

        $this->uploads = [];
        $this->dispatch('media-uploaded');
        session()->flash('signage_message', 'Medien hochgeladen.');
    }

    public function deleteMedia(int $id): void
    {
        $media = SignageMedia::where('team_id', $this->teamId())->findOrFail($id);
        $media->delete();
        session()->flash('signage_message', 'Medium gelöscht.');
    }

    public function renameMedia(int $id, string $name): void
    {
        $name = trim($name);
        if ($name === '') {
            return;
        }

        SignageMedia::where('team_id', $this->teamId())->findOrFail($id)
            ->update(['name' => mb_substr($name, 0, 255)]);
    }

    public function reloadWebsiteThumbnail(int $id): void
    {
        $media = SignageMedia::where('team_id', $this->teamId())->findOrFail($id);
        if (!$media->isWebsite()) {
            return;
        }

        // Synchron holen: sofortiges Ergebnis + konkrete Fehlermeldung,
        // unabhängig davon, ob ein Queue-Worker läuft.
        $result = app(\Platform\Signage\Services\WebsiteThumbnailService::class)->capture($media);

        if ($result['ok']) {
            session()->flash('signage_message', 'Website-Vorschau erstellt.');
        } else {
            session()->flash('signage_error', 'Vorschau fehlgeschlagen: '.($result['reason'] ?? 'unbekannt'));
        }
    }

    public function reprocessDocument(int $id): void
    {
        $media = SignageMedia::where('team_id', $this->teamId())->findOrFail($id);
        if (!$media->isDocument()) {
            return;
        }

        $media->update(['processing_status' => 'pending']);
        ConvertDocumentJob::dispatch($media->id);
        session()->flash('signage_message', 'Verarbeitung neu gestartet (benötigt einen laufenden Queue-Worker).');
    }

    // ---- Ordner --------------------------------------------------------
    public function openFolder(?int $id): void
    {
        $this->currentFolderId = $id;
        $this->resetPage();
    }

    public function createFolder(): void
    {
        $this->validate(['newFolderName' => 'required|string|max:255']);

        \Platform\Signage\Models\SignageMediaFolder::create([
            'team_id' => $this->teamId(),
            'user_id' => auth()->id(),
            'name'    => $this->newFolderName,
        ]);

        $this->reset('newFolderName');
        session()->flash('signage_message', 'Ordner erstellt.');
    }

    public function deleteFolder(int $id): void
    {
        $folder = \Platform\Signage\Models\SignageMediaFolder::where('team_id', $this->teamId())->findOrFail($id);
        // Enthaltene Medien nicht löschen, nur aus dem Ordner lösen.
        SignageMedia::where('folder_id', $folder->id)->update(['folder_id' => null]);
        $folder->delete();

        if ($this->currentFolderId === $id) {
            $this->currentFolderId = null;
        }
        session()->flash('signage_message', 'Ordner gelöscht (Medien wurden nach „Alle" verschoben).');
    }

    public function moveToFolder(int $mediaId, $folderId): void
    {
        $media = SignageMedia::where('team_id', $this->teamId())->findOrFail($mediaId);
        $folderId = (int) $folderId ?: null;

        if ($folderId) {
            // Nur in eigene Ordner verschieben.
            \Platform\Signage\Models\SignageMediaFolder::where('team_id', $this->teamId())->findOrFail($folderId);
        }

        $media->update(['folder_id' => $folderId]);
        session()->flash('signage_message', 'Medium verschoben.');
    }

    public function render()
    {
        $teamId = $this->teamId();

        [$sortCol, $sortDir] = match ($this->sortBy) {
            'created_asc' => ['created_at', 'asc'],
            'name_asc'    => ['name', 'asc'],
            'name_desc'   => ['name', 'desc'],
            default       => ['created_at', 'desc'],
        };

        $media = SignageMedia::where('team_id', $teamId)
            ->with('firstPage') // Dokument-Vorschau ohne N+1
            ->when($this->currentFolderId, fn ($q) => $q->where('folder_id', $this->currentFolderId))
            ->when($this->currentFolderId === null, fn ($q) => $q->whereNull('folder_id'))
            ->when($this->filterKind !== '', fn ($q) => $q->where('kind', $this->filterKind))
            ->orderBy($sortCol, $sortDir)
            ->paginate(24);

        $folders = \Platform\Signage\Models\SignageMediaFolder::where('team_id', $teamId)
            ->withCount('media')
            ->orderBy('name')
            ->get();

        $folderOptions = $folders->map(fn ($f) => ['value' => $f->id, 'label' => $f->name])->values()->all();

        $currentFolder = $this->currentFolderId
            ? $folders->firstWhere('id', $this->currentFolderId)
            : null;

        // Polling, solange Dokumente verarbeitet werden ODER Website-Screenshots noch fehlen.
        $hasProcessing = SignageMedia::where('team_id', $teamId)
            ->where(function ($q) {
                $q->whereIn('processing_status', ['pending', 'processing'])
                  ->orWhere(fn ($w) => $w->where('kind', 'website')->whereNull('display_token'));
            })
            ->exists();

        return view('signage::livewire.media.index', [
            'media'         => $media,
            'folders'       => $folders,
            'folderOptions' => $folderOptions,
            'currentFolder' => $currentFolder,
            'hasProcessing' => $hasProcessing,
            'maxUploadLabel' => $this->maxUploadLabel(),
        ])->layout('platform::layouts.app');
    }
}
