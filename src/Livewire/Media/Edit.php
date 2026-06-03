<?php

namespace Platform\Signage\Livewire\Media;

use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Platform\Signage\Livewire\Concerns\WithCurrentTeam;
use Platform\Signage\Models\SignageMedia;
use Platform\Signage\Models\SignageMediaFolder;
use Platform\Signage\Models\SignageScreen;

/**
 * Bearbeitet ein Datei-Medium (Bild/Video/Audio/Dokument): Name + Ordner.
 * Bei Dokumenten zusätzlich die einzelnen Seiten verwalten (löschen/sortieren).
 * Apps/Streams/Websites haben eigene Editoren.
 */
class Edit extends Component
{
    use WithCurrentTeam;

    public SignageMedia $media;
    public string $name = '';
    public ?int $folderId = null;

    public function mount(SignageMedia $media): void
    {
        abort_unless($media->team_id === $this->teamId(), 403);
        $this->media = $media;
        $this->name = (string) $media->name;
        $this->folderId = $media->folder_id;
    }

    public function removePage(int $pageId): void
    {
        if (!$this->media->isDocument()) {
            return;
        }

        $this->media->pages()->whereKey($pageId)->delete();
        $this->renumberPages();
        $this->afterPagesChanged();
        session()->flash('signage_message', 'Seite entfernt.');
    }

    public function movePage(int $pageId, string $direction): void
    {
        if (!$this->media->isDocument()) {
            return;
        }

        $pages = $this->media->pages()->orderBy('page_number')->get()->values();
        $index = $pages->search(fn ($p) => $p->id === $pageId);
        if ($index === false) {
            return;
        }

        $swapWith = $direction === 'up' ? $index - 1 : $index + 1;
        if ($swapWith < 0 || $swapWith >= $pages->count()) {
            return;
        }

        DB::transaction(function () use ($pages, $index, $swapWith) {
            $a = $pages[$index];
            $b = $pages[$swapWith];
            [$a->page_number, $b->page_number] = [$b->page_number, $a->page_number];
            $a->save();
            $b->save();
        });

        $this->afterPagesChanged();
    }

    /** Seitennummern lückenlos 1..n vergeben. */
    protected function renumberPages(): void
    {
        $pages = $this->media->pages()->orderBy('page_number')->get();
        $n = 0;
        foreach ($pages as $page) {
            $n++;
            if ($page->page_number !== $n) {
                $page->update(['page_number' => $n]);
            }
        }
    }

    protected function afterPagesChanged(): void
    {
        $this->media->update(['page_count' => $this->media->pages()->count()]);
        $this->media->refresh();
        SignageScreen::bumpForMedia($this->media->id);
    }

    public function save()
    {
        $this->validate(['name' => 'required|string|max:255']);

        $folderId = $this->folderId ?: null;
        if ($folderId) {
            SignageMediaFolder::where('team_id', $this->teamId())->findOrFail($folderId);
        }

        $this->media->update([
            'name'      => $this->name,
            'folder_id' => $folderId,
        ]);

        session()->flash('signage_message', 'Medium gespeichert.');

        return $this->redirectRoute('signage.media.index', navigate: true);
    }

    public function render()
    {
        $folderOptions = SignageMediaFolder::where('team_id', $this->teamId())
            ->orderBy('name')->get()
            ->map(fn ($f) => ['value' => $f->id, 'label' => $f->name])->values()->all();

        $pages = $this->media->isDocument()
            ? $this->media->pages()->orderBy('page_number')->get()
            : collect();

        return view('signage::livewire.media.edit', [
            'folderOptions' => $folderOptions,
            'preview'       => $this->media->previewUrl(),
            'pages'         => $pages,
        ])->layout('platform::layouts.app');
    }
}
