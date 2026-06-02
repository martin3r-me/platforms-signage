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

    // Stream einbinden (z.B. Internet-Radio oder TuneIn-Embed)
    public string $streamName = '';
    public string $streamUrl = '';
    public string $streamType = 'stream'; // 'stream' = direkter Audio-Stream, 'embed' = iframe-Player

    public function rules(): array
    {
        return [
            'uploads.*' => 'file|max:204800|mimes:jpg,jpeg,png,webp,gif,mp4,webm,mp3,aac,ogg,wav,pdf,ppt,pptx',
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

    public function addStream(): void
    {
        $this->validate([
            'streamName' => 'required|string|max:255',
            'streamUrl'  => 'required|url|max:1024',
            'streamType' => 'required|in:stream,embed',
        ]);

        // TuneIn-/Embed-Links automatisch als iframe behandeln.
        $isEmbed = $this->streamType === 'embed'
            || str_contains($this->streamUrl, '/embed')
            || str_contains($this->streamUrl, 'tunein.com/embed');

        SignageMedia::create([
            'team_id'           => $this->teamId(),
            'user_id'           => auth()->id(),
            'name'              => $this->streamName,
            'kind'              => 'audio',
            'source_type'       => 'stream',
            'stream_url'        => $this->streamUrl,
            'is_embed'          => $isEmbed,
            'processing_status' => 'ready',
        ]);

        $this->reset('streamName', 'streamUrl', 'streamType');
        session()->flash('signage_message', 'Stream hinzugefügt.');
    }

    public function deleteMedia(int $id): void
    {
        $media = SignageMedia::where('team_id', $this->teamId())->findOrFail($id);
        $media->delete();
        session()->flash('signage_message', 'Medium gelöscht.');
    }

    public function render()
    {
        $media = SignageMedia::where('team_id', $this->teamId())
            ->orderByDesc('created_at')
            ->paginate(24);

        $hasProcessing = SignageMedia::where('team_id', $this->teamId())
            ->whereIn('processing_status', ['pending', 'processing'])
            ->exists();

        return view('signage::livewire.media.index', [
            'media' => $media,
            'hasProcessing' => $hasProcessing,
        ])->layout('platform::layouts.app');
    }
}
