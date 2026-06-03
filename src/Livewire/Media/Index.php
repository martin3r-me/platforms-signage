<?php

namespace Platform\Signage\Livewire\Media;

use Illuminate\Http\UploadedFile;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;
use Platform\Core\Services\ContextFileService;
use Platform\Signage\Jobs\ConvertDocumentJob;
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

    public function rules(): array
    {
        $maxKb = (int) config('signage.max_upload_kb', 512000);

        return [
            'uploads.*' => 'file|max:'.$maxKb.'|mimes:jpg,jpeg,png,webp,gif,mp4,webm,mp3,aac,ogg,wav,pdf,ppt,pptx',
        ];
    }

    public function updatedUploads(): void
    {
        $this->validate();
        $this->saveUploads();
    }

    protected function saveUploads(): void
    {
        $teamId = $this->teamId();
        $userId = auth()->id();
        $files = app(ContextFileService::class);

        foreach ($this->uploads as $file) {
            if (!$file instanceof UploadedFile) {
                continue;
            }

            $kind = $this->determineKind($file);

            $media = SignageMedia::create([
                'team_id'           => $teamId,
                'folder_id'         => $this->currentFolderId,
                'user_id'           => $userId,
                'name'              => pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME),
                'kind'              => $kind,
                'disk'              => config('filesystems.default', 'public'),
                'path'              => '',
                'token'             => '',
                'original_name'     => $file->getClientOriginalName(),
                'processing_status' => $kind === 'document' ? 'pending' : 'ready',
            ]);

            $result = $files->uploadForContext($file, 'signage_media', $media->id, [
                'team_id'           => $teamId,
                'user_id'           => $userId,
                'keep_original'     => true,
                'generate_variants' => $kind === 'image',
            ]);

            $media->update([
                'disk'      => config('filesystems.default', 'public'),
                'path'      => $result['path'],
                'token'     => $result['token'],
                'mime_type' => $result['mime_type'] ?? $file->getMimeType(),
                'file_size' => $result['file_size'] ?? $file->getSize(),
                'width'     => $result['width'] ?? null,
                'height'    => $result['height'] ?? null,
            ]);

            if ($kind === 'document') {
                ConvertDocumentJob::dispatch($media->id);
            } elseif ($kind === 'image') {
                // Heruntergerechnete Anzeige-Variante für schnelleres Laden auf TVs.
                app(\Platform\Signage\Services\SignageImageService::class)->makeDisplayVariant($media->refresh());
            }
        }

        $this->uploads = [];
        $this->dispatch('media-uploaded');
        session()->flash('signage_message', 'Medien hochgeladen.');
    }

    protected function determineKind(UploadedFile $file): string
    {
        $mime = (string) $file->getMimeType();
        $ext = strtolower($file->getClientOriginalExtension());

        if (str_starts_with($mime, 'image/')) {
            return 'image';
        }
        if (str_starts_with($mime, 'video/')) {
            return 'video';
        }
        if (str_starts_with($mime, 'audio/')) {
            return 'audio';
        }
        if ($ext === 'pdf' || in_array($ext, ['ppt', 'pptx'], true)) {
            return 'document';
        }

        // Fallback anhand Endung.
        return match ($ext) {
            'jpg', 'jpeg', 'png', 'webp', 'gif' => 'image',
            'mp4', 'webm' => 'video',
            'mp3', 'aac', 'ogg', 'wav' => 'audio',
            default => 'document',
        };
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

        $media = SignageMedia::where('team_id', $teamId)
            ->when($this->currentFolderId, fn ($q) => $q->where('folder_id', $this->currentFolderId))
            ->when($this->currentFolderId === null, fn ($q) => $q->whereNull('folder_id'))
            ->orderByDesc('created_at')
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
        ])->layout('platform::layouts.app');
    }
}
